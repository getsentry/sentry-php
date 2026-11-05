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
     * and the profiling session is sampled. It can be started before the SDK is initialized,
     * the collected samples are then discarded if the client that is bound decides so.
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
     * Sends the samples collected so far to Sentry, the profiler keeps running.
     */
    public function flush(): void
    {
        $this->continuousProfiler->flush();
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

    /**
     * Without a client the lifecycle is not known yet, so the profiler is controlled manually until a client
     * is bound, which decides whether the collected samples are kept.
     */
    private function isManualLifecycle(): bool
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        return $client === null || $client->getOptions()->getProfileLifecycle() === 'manual';
    }
}
