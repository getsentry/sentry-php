<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\ClientInterface;
use Sentry\ClientReport\ClientReportAggregator;
use Sentry\ClientReport\Reason;
use Sentry\Event;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Transport\DataCategory;
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
     * @var int The number of requests running code, which resumed the profiler and did not pause it yet
     */
    private $activeRequestCount = 0;

    /**
     * @var bool Whether the running profiler is paused
     */
    private $paused = false;

    /**
     * @var float[] The times the profiler was paused, as seconds since the start of the running profiler
     */
    private $pauseTimestamps = [];

    /**
     * @var bool|null Whether the profiling session is sampled, false once continuous profiling is disabled,
     *                undecided while a profiler started before a client was bound
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
     *
     * A running profiler that is paused is resumed.
     */
    public function start(): void
    {
        $this->restartInForkedProcess();

        if ($this->profiler !== null) {
            $this->resumeTimer();

            return;
        }

        // The profiling session is decided once, so it is not restarted while no client is bound after a re-init
        // and the warnings about a disabled session are not repeated
        if ($this->sampled === false) {
            return;
        }

        $client = SentrySdk::getClient();
        $options = $client instanceof NoOpClient ? null : $client->getOptions();

        if ($options !== null && !$this->isSessionEnabled($options)) {
            return;
        }

        if (!\extension_loaded('excimer')) {
            // The warning is logged once a client is bound, the session is only disabled then
            if ($options !== null) {
                $options->getLoggerOrNullLogger()->warning('The continuous profiler was started but is not available because the "excimer" extension is not loaded.');

                $this->sampled = false;
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
                SentrySdk::getClient()->getOptions()->getLoggerOrNullLogger()->error('Failed to complete the profile chunks.', ['exception' => $exception]);
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

    /**
     * Pauses the profiler once every request that resumed it paused it again, e.g. a worker that finished its last
     * request and waits for the next one. The profiler is also paused by a request that never resumed it, e.g. the
     * first request of a worker that started the profiler. The time until the profiler is resumed is sent as idle,
     * instead of being attributed to the stack of the sample taken before it.
     *
     * The pending chunks are captured while the profiler is paused, as no code is sampled in the meantime.
     */
    public function pause(): void
    {
        $this->restartInForkedProcess();

        $this->activeRequestCount = max(0, $this->activeRequestCount - 1);

        if ($this->activeRequestCount > 0 || $this->paused) {
            return;
        }

        if ($this->profiler === null || $this->startTimestamp === null) {
            return;
        }

        $this->profiler->stop();

        $this->paused = true;
        $this->pauseTimestamps[] = microtime(true) - $this->startTimestamp;

        $this->capturePendingChunks();
    }

    /**
     * Resumes the paused profiler, e.g. a worker that received a request. Each request resumes the profiler before
     * it pauses it, so that the profiler keeps running while another request is handled.
     */
    public function resume(): void
    {
        $this->restartInForkedProcess();

        ++$this->activeRequestCount;

        $this->resumeTimer();
    }

    public function capturePendingChunks(): void
    {
        $this->restartInForkedProcess();

        if ($this->profiler !== null && microtime(true) >= $this->nextFlushTimestamp) {
            $this->profiler->flush();
        }

        $client = SentrySdk::getClient();

        // Without a client the chunks stay pending until they can be sent
        if ($client instanceof NoOpClient || $this->pendingChunks->isEmpty()) {
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

    public function isRunning(): bool
    {
        return $this->profiler !== null;
    }

    public function isPaused(): bool
    {
        return $this->paused;
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
     * Merges the pause timestamps into the entries obtained from Excimer. This is necessary for runtimes such
     * as FrankenPHP that can remain active between requests. Without idle samples, it would not be immediately
     * clear when a request ended, and it would inflate durations of the last sample in a request.
     *
     * @param iterable<\ExcimerLogEntry> $entries
     *
     * @return array<int, \ExcimerLogEntry|ExcimerLogStackEntry>
     */
    private function addIdleSamples(iterable $entries): array
    {
        $entriesWithIdleSamples = [];
        $pauseIndex = 0;

        // Merge paused timestamps between entries in case multiple pauses have happened before Excimers flush
        // callback fired.
        foreach ($entries as $entry) {
            while (isset($this->pauseTimestamps[$pauseIndex]) && $this->pauseTimestamps[$pauseIndex] < $entry->getTimestamp()) {
                $entriesWithIdleSamples[] = ['trace' => [], 'timestamp' => $this->pauseTimestamps[$pauseIndex]];

                ++$pauseIndex;
            }

            $entriesWithIdleSamples[] = $entry;
        }

        // Add all remaining pause samples at the end
        while (isset($this->pauseTimestamps[$pauseIndex])) {
            $entriesWithIdleSamples[] = ['trace' => [], 'timestamp' => $this->pauseTimestamps[$pauseIndex]];

            ++$pauseIndex;
        }

        $this->pauseTimestamps = [];

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

        $client = SentrySdk::getClient();
        if ($client instanceof NoOpClient) {
            return;
        }

        if ($sampleCount < ProfileChunk::MIN_SAMPLE_COUNT) {
            $client->getOptions()->getLoggerOrNullLogger()->debug('The profile chunk does not contain enough samples, the profile chunk will be discarded.');

            ClientReportAggregator::getInstance()->add(DataCategory::profileChunk(), Reason::insufficientData(), 1);

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
        $this->paused = false;
        $this->pauseTimestamps = [];

        // A forked process inherits the completed chunks, which the parent process captures itself
        $this->pendingChunks->drain();
    }

    private function resumeTimer(): void
    {
        if ($this->profiler === null || !$this->paused) {
            return;
        }

        $this->profiler->start();

        $this->paused = false;
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

        // The parent process finishes its own root spans, so a profiler running for them is not restarted
        $restart = $this->activeRootSpans === [];
        $this->activeRootSpans = [];

        // The parent process finishes its own requests, so they do not keep the profiler of this process running
        $this->activeRequestCount = 0;

        // The timer of the inherited profiler does not exist in this process, so tearing it down emits warnings
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $this->clearProfiler();
        } finally {
            restore_error_handler();
        }

        if ($restart) {
            $this->start();
        }
    }

    /**
     * Decides whether the profiling session is sampled, the session is disabled instead if transaction based
     * profiling is configured by the options.
     */
    private function isSessionEnabled(Options $options): bool
    {
        if ($options->getProfilesSampleRate() !== null || $options->getProfilesSampler() !== null) {
            $options->getLoggerOrNullLogger()->warning('Continuous profiling is disabled because transaction based profiling is configured with the "profiles_sample_rate" or "profiles_sampler" option.');

            $this->sampled = false;

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
