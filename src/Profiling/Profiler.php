<?php

declare(strict_types=1);

namespace Sentry\Profiling;

/**
 * @internal
 */
final class Profiler
{
    private ?\ExcimerProfiler $profiler;

    private ?Profile $profile;

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
        }
    }

    public function stop(): void
    {
        if (null !== $this->profiler) {
            $this->profiler->stop();
            $this->profile->setExcimerLog($this->profiler->flush());
        }
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    private function initProfiler(): void
    {
        if (\extension_loaded('excimer')) {
            $this->profiler = new \ExcimerProfiler();
            $this->profile->setStartTime(microtime(true));

            $this->profiler->setEventType(EXCIMER_REAL);
            $this->profiler->setPeriod(self::SAMPLE_RATE);
            $this->profiler->setMaxDepth(self::MAX_STACK_DEPTH);
        }
    }
}
