<?php

declare(strict_types=1);

namespace Sentry\Profiles;

use Sentry\EventId;
use Sentry\Options;

class Profiles
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var ProfilesAggregator
     */
    private $aggregator;

    /**
     * @var Profiler
     */
    private $profiler;

    private function __construct(?Options $options = null)
    {
        $this->profiler = new Profiler($options);

        $this->aggregator = new ProfilesAggregator();
        $this->aggregator->setStartTimeStamp($this->profiler->getStartTimeStamp());
        $this->aggregator->setProfilerId($this->profiler->getProfilerId());
    }

    public static function getInstance(?Options $options = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($options);
        }

        return self::$instance;
    }

    public function getProfiler(): Profiler
    {
        return $this->profiler;
    }

    public function start(): void
    {
        $this->profiler->start();
    }

    public function stop(): void
    {
        $excimerLog = $this->profiler->stop();

        if ($excimerLog !== null) {
            $this->aggregator->add($excimerLog);
        }
    }

    /**
     * Flush the captured profile chunks and send them to Sentry.
     */
    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }

    /**
     * Get the profiles aggregator.
     *
     * @internal
     */
    public function aggregator(): ProfilesAggregator
    {
        return $this->aggregator;
    }
}
