<?php

declare(strict_types=1);

namespace Sentry\Profiling;

use Sentry\SentrySdk;

class Profiles
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var ContinuousProfiler
     */
    private $continuousProfiler;

    private function __construct()
    {
        $this->continuousProfiler = new ContinuousProfiler();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Starts the continuous profiler if the "profile_lifecycle" option is set to "manual"
     * and the profiling session is sampled.
     */
    public function start(): void
    {
        if (!$this->isManualLifecycle()) {
            return;
        }

        $this->continuousProfiler->start();
    }

    /**
     * Stops the continuous profiler if the "profile_lifecycle" option is set to "manual"
     * and sends the collected profile to Sentry.
     */
    public function stop(): void
    {
        if (!$this->isManualLifecycle()) {
            return;
        }

        $this->continuousProfiler->stop();
    }

    /**
     * Get the continuous profiler.
     *
     * @internal
     */
    public function continuousProfiler(): ContinuousProfiler
    {
        return $this->continuousProfiler;
    }

    private function isManualLifecycle(): bool
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        return $client !== null && $client->getOptions()->getProfileLifecycle() === 'manual';
    }
}
