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
        $this->startTimestamp = microtime(true);
        $this->profiler = new \ExcimerProfiler();
        $this->profilerId = SentryUid::generate();

        $this->profiler->setEventType(\EXCIMER_REAL);
        $this->profiler->setPeriod(self::SAMPLE_RATE);
        $this->profiler->setMaxDepth(self::MAX_STACK_DEPTH);

        $this->profiler->start();
    }

    public function stop(): void
    {
        if ($this->profiler === null || $this->profilerId === null || $this->startTimestamp === null) {
            return;
        }

        $this->profiler->stop();

        $excimerLog = $this->profiler->flush();
        $profilerId = $this->profilerId;
        $startTimestamp = $this->startTimestamp;

        $this->profiler = null;
        $this->profilerId = null;
        $this->startTimestamp = null;

        $this->captureChunk($profilerId, $startTimestamp, $excimerLog);
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
     * @param \ExcimerLog $excimerLog
     */
    private function captureChunk(string $profilerId, float $startTimestamp, $excimerLog): void
    {
        $client = SentrySdk::getCurrentHub()->getClient();
        if ($client === null) {
            return;
        }

        if ($excimerLog->count() < ProfileChunk::MIN_SAMPLE_COUNT) {
            $client->getOptions()->getLoggerOrNullLogger()->debug('The profile chunk does not contain enough samples, the profile chunk will be discarded.');

            return;
        }

        $buffer = new ProfileDataBuffer($client->getOptions());

        foreach ($excimerLog as $entry) {
            $buffer->add($entry);
        }

        $profileChunk = new ProfileChunk($profilerId, $startTimestamp, $buffer->takeProfileData(), $client->getOptions());

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
