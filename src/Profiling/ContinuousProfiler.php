<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Util\SentryUid;
use Sentry\Util\TelemetryStorage;

/**
 * @phpstan-import-type ExcimerLogStackEntry from ExcimerLogConverter
 *
 * @internal
 */
final class ContinuousProfiler
{
    /**
     * @var float The sample rate (10.01ms/101 Hz)
     */
    private const SAMPLE_RATE = 0.0101;

    /**
     * @var int
     */
    private const MAX_STACK_DEPTH = 128;

    /**
     * @var int The maximum number of samples in a profile chunk (~60s at 101 Hz)
     */
    private const MAX_SAMPLES_PER_CHUNK = 6060;

    /**
     * @var float The maximum duration of a profile chunk in seconds, which fits a full chunk of samples (slightly
     *            more than 60 seconds) and stays below the 66 seconds Relay accepts
     */
    private const MAX_CHUNK_DURATION = 65.0;

    /**
     * @var int
     */
    private const MAX_PENDING_CHUNKS = 10;

    /**
     * @var float The time in seconds after which the samples are flushed when capturing the pending chunks, shorter than
     *            the maximum duration of a chunk, so that they fit into one chunk
     */
    private const FLUSH_INTERVAL = 60.0;

    /**
     * @var \ExcimerProfiler|null
     */
    private $profiler;

    /**
     * @var string|null
     */
    private $profilerId;

    /**
     * @var int|false|null The ID of the process the running profiler samples
     */
    private $processId;

    /**
     * @var float|null The start time of the running profiler as a Unix timestamp with microseconds
     */
    private $startTimestamp;

    /**
     * @var float
     */
    private $nextFlushTimestamp = 0.0;

    /**
     * @var array<int, array{float, float|null}> The start and end of the times the application was idle, as seconds since
     *                                           the start of the running profiler, without an end while it is still idle
     */
    private $idlePeriods = [];

    /**
     * @var bool|null Whether the profiling session is sampled, undecided while a profiler started before a client was bound
     */
    private $sampled;

    /**
     * @var array<string, string> The runtime context IDs of the sampled root spans the profiler is running for, keyed by span ID
     */
    private $activeRootSpans = [];

    /**
     * @var TelemetryStorage<ProfileChunk>
     */
    private $pendingChunks;

    /**
     * @var int
     */
    private $maxSamplesPerChunk;

    /**
     * @var float
     */
    private $maxChunkDuration;

    /**
     * @var float
     */
    private $flushInterval;

    public function __construct(int $maxSamplesPerChunk = self::MAX_SAMPLES_PER_CHUNK, float $maxChunkDuration = self::MAX_CHUNK_DURATION, float $flushInterval = self::FLUSH_INTERVAL)
    {
        /** @var TelemetryStorage<ProfileChunk> $pendingChunks */
        $pendingChunks = TelemetryStorage::bounded(self::MAX_PENDING_CHUNKS);

        $this->maxSamplesPerChunk = $maxSamplesPerChunk;
        $this->maxChunkDuration = $maxChunkDuration;
        $this->flushInterval = $flushInterval;
        $this->pendingChunks = $pendingChunks;
    }

    /**
     * The profiler is started even if the SDK is not initialized yet. Once init is called, it will keep or discard
     * the samples, based on the configured options. Only for manual profiling, `trace` will start with the
     * transaction anyway.
     */
    public function start(): void
    {
        $this->restartInForkedProcess();

        if ($this->profiler !== null) {
            return;
        }

        $client = SentrySdk::getCurrentHub()->getClient();
        $options = $client !== null ? $client->getOptions() : null;

        if ($options !== null && !$this->isSessionEnabled($options)) {
            return;
        }

        if (!\extension_loaded('excimer')) {
            if ($options !== null) {
                $options->getLoggerOrNullLogger()->warning('The continuous profiler was started but is not available because the "excimer" extension is not loaded.');
            }

            return;
        }

        // Excimer measures sample timestamps from the construction of the profiler
        $startTimestamp = microtime(true);
        $profiler = new \ExcimerProfiler();
        $profilerId = SentryUid::generate();

        $profiler->setEventType(\EXCIMER_REAL);
        $profiler->setPeriod(self::SAMPLE_RATE);
        $profiler->setMaxDepth(self::MAX_STACK_DEPTH);
        $profiler->setFlushCallback(function ($excimerLog): void {
            $this->nextFlushTimestamp = microtime(true) + $this->flushInterval;

            // The callback runs while sampling the application, so nothing may be thrown into the application code
            try {
                $this->completeChunks($excimerLog);
            } catch (\Throwable $exception) {
                $client = SentrySdk::getCurrentHub()->getClient();

                if ($client !== null) {
                    $client->getOptions()->getLoggerOrNullLogger()->error('Failed to complete the profile chunks.', ['exception' => $exception]);
                }
            }
        }, $this->maxSamplesPerChunk);

        $this->profiler = $profiler;
        $this->profilerId = $profilerId;
        $this->processId = getmypid();
        $this->startTimestamp = $startTimestamp;
        $this->nextFlushTimestamp = $startTimestamp + $this->flushInterval;

        $profiler->start();
    }

    /**
     * Starts the profiler for a sampled root span, it keeps running until all of them are finished.
     */
    public function startForRootSpan(string $spanId): void
    {
        $this->start();

        if ($this->profiler !== null) {
            $this->activeRootSpans[$spanId] = SentrySdk::getCurrentRuntimeContext()->getId();
        }
    }

    public function stopForRootSpan(string $spanId): void
    {
        if (!isset($this->activeRootSpans[$spanId])) {
            return;
        }

        unset($this->activeRootSpans[$spanId]);

        if ($this->activeRootSpans === []) {
            $this->stop();
        }
    }

    /**
     * Releases the root spans of an ending runtime context that were never finished.
     */
    public function stopForRuntimeContext(string $runtimeContextId): void
    {
        $released = false;

        foreach ($this->activeRootSpans as $spanId => $rootSpanRuntimeContextId) {
            if ($rootSpanRuntimeContextId === $runtimeContextId) {
                unset($this->activeRootSpans[$spanId]);

                $released = true;
            }
        }

        if ($released && $this->activeRootSpans === []) {
            $this->stop();
        }
    }

    /**
     * Keeps a profiler started before a client was bound running if it is used manually and the
     * profiling session is sampled, otherwise its samples are discarded.
     */
    public function onClientBound(ClientInterface $client): void
    {
        if ($this->profiler === null || $this->sampled !== null) {
            return;
        }

        $options = $client->getOptions();

        if ($options->getProfileLifecycle() === 'manual' && $this->isSessionEnabled($options)) {
            return;
        }

        $this->profiler->stop();

        $this->clearProfiler();
    }

    public function stop(): void
    {
        $this->restartInForkedProcess();

        if ($this->profiler === null) {
            return;
        }

        $this->profiler->stop();

        // Passes the remaining samples to the flush callback
        $this->profiler->flush();

        $this->capturePendingChunks();

        $this->clearProfiler();

        $this->activeRootSpans = [];
    }

    public function flush(): void
    {
        $this->restartInForkedProcess();

        if ($this->profiler === null) {
            return;
        }

        $this->profiler->flush();

        $this->capturePendingChunks();
    }

    public function capturePendingChunks(): void
    {
        $this->restartInForkedProcess();

        if ($this->profiler !== null && microtime(true) >= $this->nextFlushTimestamp) {
            $this->profiler->flush();
        }

        $client = SentrySdk::getCurrentHub()->getClient();

        // Without a client the chunks stay pending until they can be sent
        if ($client === null || $this->pendingChunks->isEmpty()) {
            return;
        }

        // Stopping or flushing the profiler and ending a runtime context must not fail because of a chunk
        try {
            foreach ($this->pendingChunks->drain() as $profileChunk) {
                $client->captureEvent(Event::createProfileChunk()->setProfileChunk($profileChunk));
            }
        } catch (\Throwable $exception) {
            $client->getOptions()->getLoggerOrNullLogger()->error('Failed to send the profile chunks.', ['exception' => $exception]);
        }
    }

    /**
     * Marks that the application became idle, e.g. a worker waiting for the next request, until it is marked busy
     * again. The time no code runs in between is sent as idle instead of being attributed to the stack of the next
     * sample, e.g. a health check that runs while the application is idle.
     *
     * The pending chunks are captured afterwards, so that their samples end with the idle time. Otherwise, a long
     * idle time could not be added to a chunk, as the next sample is too far away to share one.
     */
    public function markIdle(): void
    {
        $this->restartInForkedProcess();

        if ($this->startTimestamp === null) {
            return;
        }

        $idleTimestamp = microtime(true) - $this->startTimestamp;
        $lastIndex = \count($this->idlePeriods) - 1;

        // Within a busy time shorter than a tick, no sample can cover several ticks, so the idle time continues
        if ($lastIndex >= 0 && ($this->idlePeriods[$lastIndex][1] === null || $idleTimestamp - $this->idlePeriods[$lastIndex][1] < self::SAMPLE_RATE)) {
            $this->idlePeriods[$lastIndex][1] = null;
        } else {
            $this->idlePeriods[] = [$idleTimestamp, null];
        }

        $this->capturePendingChunks();
    }

    /**
     * Marks that the application is busy again, e.g. a worker that starts handling a request.
     */
    public function markBusy(): void
    {
        $this->restartInForkedProcess();

        $lastIndex = \count($this->idlePeriods) - 1;

        if ($this->startTimestamp === null || $lastIndex < 0 || $this->idlePeriods[$lastIndex][1] !== null) {
            return;
        }

        $this->idlePeriods[$lastIndex][1] = microtime(true) - $this->startTimestamp;
    }

    public function isRunning(): bool
    {
        return $this->profiler !== null;
    }

    /**
     * Gets the ID of the running profiler, which is restarted first if the process was forked,
     * so that spans created in the forked process are linked to the profiler sampling it.
     */
    public function getProfilerId(): ?string
    {
        $this->restartInForkedProcess();

        return $this->profilerId;
    }

    /**
     * When calling blocking operations, excimer may not produce samples for the entire duration,
     * so the duration between the first and last sample can exceed the maximum duration of a chunk,
     * the samples are then split into several chunks.
     *
     * @param iterable<\ExcimerLogEntry> $entries
     */
    private function completeChunks(iterable $entries): void
    {
        $chunkEntries = [];
        $chunkStartTimestamp = 0.0;

        foreach ($this->addIdleSamples($entries) as $entry) {
            $timestamp = $entry instanceof \ExcimerLogEntry ? $entry->getTimestamp() : $entry['timestamp'];

            if ($chunkEntries !== [] && $timestamp - $chunkStartTimestamp >= $this->maxChunkDuration) {
                $this->completeChunk($chunkEntries);

                $chunkEntries = [];
            }

            if ($chunkEntries === []) {
                $chunkStartTimestamp = $timestamp;
            }

            $chunkEntries[] = $entry;
        }

        $this->completeChunk($chunkEntries);
    }

    /**
     * Excimer only samples while code runs, so it records the ticks of an idle application with the next sample.
     * A sample without a stack is added before such a sample taken while the application was idle, at the first of
     * its ticks, as well as after the last sample if the application became idle since then.
     *
     * @param iterable<\ExcimerLogEntry> $entries
     *
     * @return array<int, \ExcimerLogEntry|ExcimerLogStackEntry>
     */
    private function addIdleSamples(iterable $entries): array
    {
        $entriesWithIdleSamples = [];
        $idleIndex = 0;
        $previousTimestamp = null;

        foreach ($entries as $entry) {
            $timestamp = $entry->getTimestamp();
            $eventCount = $entry->getEventCount();

            while (isset($this->idlePeriods[$idleIndex]) && $this->idlePeriods[$idleIndex][1] !== null && $this->idlePeriods[$idleIndex][1] < $timestamp) {
                ++$idleIndex;
            }

            $idlePeriod = $this->idlePeriods[$idleIndex] ?? null;

            if ($idlePeriod !== null && $idlePeriod[0] <= $timestamp && $eventCount > 1) {
                if ($previousTimestamp !== null) {
                    // The ticks fired evenly since the previous sample, so the first one fired one tick after it
                    $firstTickTimestamp = $previousTimestamp + ($timestamp - $previousTimestamp) / $eventCount;
                } else {
                    // Without a previous sample, the ticks fired one sample period apart until this sample
                    $firstTickTimestamp = $timestamp - ($eventCount - 1) * self::SAMPLE_RATE;
                }

                // The idle sample starts at the first tick, or when the application became idle if that was later
                $entriesWithIdleSamples[] = ['trace' => [], 'timestamp' => max($idlePeriod[0], $firstTickTimestamp)];
            }

            $entriesWithIdleSamples[] = $entry;
            $previousTimestamp = $timestamp;
        }

        $lastIdlePeriod = $this->idlePeriods[\count($this->idlePeriods) - 1] ?? null;

        // The application became idle after the last sample, so the samples end with the idle time
        if ($lastIdlePeriod !== null && $previousTimestamp !== null && $lastIdlePeriod[0] > $previousTimestamp) {
            $entriesWithIdleSamples[] = ['trace' => [], 'timestamp' => $lastIdlePeriod[0]];
        }

        // Only the idle time that did not end yet matters for the next samples
        $this->idlePeriods = [];

        if ($lastIdlePeriod !== null && $lastIdlePeriod[1] === null) {
            $this->idlePeriods[] = $lastIdlePeriod;
        }

        return $entriesWithIdleSamples;
    }

    /**
     * @param array<int, \ExcimerLogEntry|ExcimerLogStackEntry> $entries
     */
    private function completeChunk(array $entries): void
    {
        if ($this->profilerId === null || $this->startTimestamp === null) {
            return;
        }

        // Like Relay, only the samples with a stack are counted
        $sampleCount = \count(array_filter($entries, static function ($entry): bool {
            return $entry instanceof \ExcimerLogEntry;
        }));

        // Samples collected before a client was bound are only sent once it decided to keep the session
        if ($sampleCount === 0 || $this->sampled === null) {
            return;
        }

        $client = SentrySdk::getCurrentHub()->getClient();
        if ($client === null) {
            return;
        }

        if ($sampleCount < ProfileChunk::MIN_SAMPLE_COUNT) {
            $client->getOptions()->getLoggerOrNullLogger()->debug('The profile chunk does not contain enough samples, the profile chunk will be discarded.');

            return;
        }

        $profileData = ExcimerLogConverter::convert($entries, $client->getOptions());

        $this->pendingChunks->push(new ProfileChunk($this->profilerId, $this->startTimestamp, $profileData, $client->getOptions()));
    }

    private function clearProfiler(): void
    {
        // Clear the flush callback so that the samples are discarded
        if ($this->profiler !== null) {
            $this->profiler->clearFlushCallback();
        }

        $this->profiler = null;
        $this->profilerId = null;
        $this->processId = null;
        $this->startTimestamp = null;
        $this->idlePeriods = [];

        // A forked process inherits the completed chunks, which the parent process captures itself
        $this->pendingChunks->drain();
    }

    /**
     * Excimer does not sample in a forked process, so the profiler inherited from the parent process
     * is replaced by a new one sampling this process, while the parent process keeps its own samples.
     */
    private function restartInForkedProcess(): void
    {
        if ($this->profiler === null || $this->processId === getmypid()) {
            return;
        }

        // The timer of the inherited profiler does not exist in this process, so tearing it down emits warnings
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $this->clearProfiler();
        } finally {
            restore_error_handler();
        }

        $this->start();
    }

    /**
     * Decides whether the profiling session is sampled, unless continuous profiling is disabled by the options.
     */
    private function isSessionEnabled(Options $options): bool
    {
        if ($options->getProfilesSampleRate() !== null || $options->getProfilesSampler() !== null) {
            $options->getLoggerOrNullLogger()->warning('Continuous profiling is disabled because transaction based profiling is configured with the "profiles_sample_rate" or "profiles_sampler" option.');

            return false;
        }

        if ($this->sampled === null) {
            $this->sampled = $this->isSessionSampled($options->getProfileSessionSampleRate());
        }

        return $this->sampled;
    }

    private function isSessionSampled(?float $sampleRate): bool
    {
        if ($sampleRate === null || $sampleRate <= 0.0) {
            return false;
        }

        if ($sampleRate >= 1.0) {
            return true;
        }

        return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() < $sampleRate;
    }
}
