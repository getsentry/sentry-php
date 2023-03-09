<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * @internal
 */
final class Profiler
{
    /**
     * @var \ExcimerProfiler|null
     */
    private $profiler;

    /**
     * @var Profile
     */
    private $profile;

    /**
     * @var float The sample rate (10.01ms/101 Hz)
     */
    private const SAMPLE_RATE = 0.0101;

    /**
     * @var int The maximum stack depth
     */
    private const MAX_STACK_DEPTH = 128;

    public function __construct()
    {
        $this->profile = new Profile();

        $this->initProfiler();
    }

    public function start(): void
    {
        if (null !== $this->profiler) {
            $this->profiler->start();

            $this->profile->setStartTime($this->currentTimestampInMicroseconds());
            $this->profile->setStartTimeStamp(microtime(true));
        }
    }

    public function stop(): void
    {
        if (null !== $this->profiler) {
            $this->profiler->stop();

            $this->profile->setStopTime($this->currentTimestampInMicroseconds());
            $this->profile->setExcimerLog($this->profiler->flush());
        }
    }

    public function getProfile(): Profile
    {
        return $this->profile;
    }

    private function initProfiler(): void
    {
        if (\extension_loaded('excimer') && \PHP_VERSION_ID >= 70300) {
            $this->profiler = new \ExcimerProfiler();
            $this->profiler->setEventType(EXCIMER_REAL);
            $this->profiler->setPeriod(self::SAMPLE_RATE);
            $this->profiler->setMaxDepth(self::MAX_STACK_DEPTH);

            $this->profile->setInitTime(hrtime(true));
        }
    }

    /**
     * Get the system's highest resolution of time possible.
     *
     * If the `hrtime` function is available, it will be used to get a nanosecond precision point in time.
     *
     * Otherwise, the `microtime` function will be used to get a microsecond precision point in time (that will be converted to look like a nanosecond precise timestamp).
     */
    private function currentTimestampInMicroseconds(): int
    {
        // Is the `hrtime` function available to get a nanosecond precision point in time?
        if (\function_exists('hrtime')) {
            return hrtime(true);
        }

        return (int) (microtime(true) * 1e9);
    }
}
