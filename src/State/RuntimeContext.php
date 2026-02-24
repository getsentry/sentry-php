<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Logs\LogsAggregator;
use Sentry\Metrics\MetricsAggregator;

/**
 * Holds runtime-local state for a single unit of work.
 *
 * A unit of work can be an HTTP request, a queue job, a worker task, or any
 * explicit lifecycle wrapped with startContext()/endContext().
 *
 * @internal
 */
final class RuntimeContext
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var HubInterface
     */
    private $hub;

    /**
     * @var LogsAggregator
     */
    private $logsAggregator;

    /**
     * @var MetricsAggregator
     */
    private $metricsAggregator;

    public function __construct(string $id, HubInterface $hub)
    {
        $this->id = $id;
        $this->hub = $hub;
        $this->logsAggregator = new LogsAggregator();
        $this->metricsAggregator = new MetricsAggregator();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHub(): HubInterface
    {
        return $this->hub;
    }

    public function setHub(HubInterface $hub): void
    {
        $this->hub = $hub;
    }

    public function getLogsAggregator(): LogsAggregator
    {
        return $this->logsAggregator;
    }

    public function getMetricsAggregator(): MetricsAggregator
    {
        return $this->metricsAggregator;
    }
}
