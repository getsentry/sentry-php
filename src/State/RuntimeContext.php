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
 * Storage implementations should treat instances as opaque values owned by the
 * SDK and must not create or mutate them directly.
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

    /**
     * @internal
     */
    public function __construct(string $id, HubInterface $hub)
    {
        $this->id = $id;
        $this->hub = $hub;
        $this->logsAggregator = new LogsAggregator();
        $this->metricsAggregator = new MetricsAggregator();
    }

    /**
     * @internal
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @internal
     */
    public function getHub(): HubInterface
    {
        return $this->hub;
    }

    /**
     * @internal
     */
    public function setHub(HubInterface $hub): void
    {
        $this->hub = $hub;
    }

    /**
     * @internal
     */
    public function getLogsAggregator(): LogsAggregator
    {
        return $this->logsAggregator;
    }

    /**
     * @internal
     */
    public function getMetricsAggregator(): MetricsAggregator
    {
        return $this->metricsAggregator;
    }
}
