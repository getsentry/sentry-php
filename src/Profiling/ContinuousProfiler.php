<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Util\SentryUid;
use Sentry\Util\TelemetryStorage;

/**
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
     * @var float|null The start time of the running profiler as a Unix timestamp with microseconds
     */
    private $startTimestamp;

    /**
     * @var float
     */
    private $nextFlushTimestamp = 0.0;

    /**
     * @var bool|null
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

    public function start(): void
    {
        if ($this->profiler !== null) {
            return;
        }

        $client = SentrySdk::getCurrentHub()->getClient();
        if ($client === null) {
            return;
        }

        $options = $client->getOptions();
        $logger = $options->getLoggerOrNullLogger();

        if ($options->getProfilesSampleRate() !== null || $options->getProfilesSampler() !== null) {
            $logger->warning('Continuous profiling is disabled because transaction based profiling is configured with the "profiles_sample_rate" or "profiles_sampler" option.');

            return;
        }

        if ($this->sampled === null) {
            $this->sampled = $this->isSessionSampled($options->getProfileSessionSampleRate());
        }

        if (!$this->sampled) {
            return;
        }

        if (!\extension_loaded('excimer')) {
            $logger->warning('The continuous profiler was started but is not available because the "excimer" extension is not loaded.');

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

    public function stop(): void
    {
        if ($this->profiler === null) {
            return;
        }

        $this->profiler->stop();

        // Passes the remaining samples to the flush callback
        $this->profiler->flush();

        $this->capturePendingChunks();

        $this->profiler = null;
        $this->profilerId = null;
        $this->startTimestamp = null;
        $this->activeRootSpans = [];
    }

    public function flush(): void
    {
        if ($this->profiler === null) {
            return;
        }

        $this->profiler->flush();

        $this->capturePendingChunks();
    }

    public function capturePendingChunks(): void
    {
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

    public function isRunning(): bool
    {
        return $this->profiler !== null;
    }

    public function getProfilerId(): ?string
    {
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

        foreach ($entries as $entry) {
            if ($chunkEntries !== [] && $entry->getTimestamp() - $chunkEntries[0]->getTimestamp() >= $this->maxChunkDuration) {
                $this->completeChunk($chunkEntries);

                $chunkEntries = [];
            }

            $chunkEntries[] = $entry;
        }

        $this->completeChunk($chunkEntries);
    }

    /**
     * @param \ExcimerLogEntry[] $entries
     */
    private function completeChunk(array $entries): void
    {
        if ($this->profilerId === null || $this->startTimestamp === null) {
            return;
        }

        $sampleCount = \count($entries);

        if ($sampleCount === 0) {
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
