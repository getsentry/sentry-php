<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Util\SentryUid;

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
     * @var float The maximum duration of a profile chunk in seconds, Relay rejects chunks longer than 66 seconds
     */
    private const MAX_CHUNK_DURATION = 60.0;

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
     * @var bool|null
     */
    private $sampled;

    /**
     * @var ProfileDataBuffer|null The samples of the next chunk, converted as they are collected
     */
    private $buffer;

    /**
     * @var int
     */
    private $maxSamplesPerChunk;

    /**
     * @var float
     */
    private $maxChunkDuration;

    public function __construct(int $maxSamplesPerChunk = self::MAX_SAMPLES_PER_CHUNK, float $maxChunkDuration = self::MAX_CHUNK_DURATION)
    {
        $this->maxSamplesPerChunk = $maxSamplesPerChunk;
        $this->maxChunkDuration = $maxChunkDuration;
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
            // The callback runs while sampling the application, so nothing may be thrown into the application code
            try {
                $this->bufferSamples($excimerLog);
            } catch (\Throwable $exception) {
                $client = SentrySdk::getCurrentHub()->getClient();

                if ($client !== null) {
                    $client->getOptions()->getLoggerOrNullLogger()->error('Failed to send the profile chunk.', ['exception' => $exception]);
                }
            }
        }, $this->maxSamplesPerChunk);

        $this->profiler = $profiler;
        $this->profilerId = $profilerId;
        $this->startTimestamp = $startTimestamp;
        $this->buffer = new ProfileDataBuffer($options);

        $profiler->start();
    }

    public function stop(): void
    {
        if ($this->profiler === null) {
            return;
        }

        $this->profiler->stop();

        // Passes the remaining samples to the flush callback
        $this->profiler->flush();

        $this->captureBuffer();

        $this->profiler = null;
        $this->profilerId = null;
        $this->startTimestamp = null;
        $this->buffer = null;
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
     * Buffers the samples, the buffer is sent once it holds enough samples or would exceed the
     * maximum duration of a chunk.
     *
     * When calling blocking operations, excimer may not produce samples for the entire duration,
     * so the duration between the first and last sample can exceed the maximum duration of a chunk
     * long before the buffer is full.
     *
     * @param iterable<\ExcimerLogEntry> $entries
     */
    private function bufferSamples(iterable $entries): void
    {
        $buffer = $this->buffer;

        if ($buffer === null) {
            return;
        }

        foreach ($entries as $entry) {
            $bufferStartTimestamp = $buffer->getStartTimestamp();

            if ($bufferStartTimestamp !== null && $entry->getTimestamp() - $bufferStartTimestamp >= $this->maxChunkDuration) {
                $this->captureBuffer();
            }

            $buffer->add($entry);
        }

        if ($buffer->getSampleCount() >= $this->maxSamplesPerChunk) {
            $this->captureBuffer();
        }
    }

    private function captureBuffer(): void
    {
        if ($this->buffer === null || $this->profilerId === null || $this->startTimestamp === null) {
            return;
        }

        $profileData = $this->buffer->takeProfileData();
        $sampleCount = \count($profileData->getSamples());

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

        $profileChunk = new ProfileChunk($this->profilerId, $this->startTimestamp, $profileData, $client->getOptions());

        $client->captureEvent(Event::createProfileChunk()->setProfileChunk($profileChunk));
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
