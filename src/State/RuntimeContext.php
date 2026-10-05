<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\EventId;
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
     * @var IsolationScope
     */
    private $isolationScope;

    /**
     * @var LogsAggregator
     */
    private $logsAggregator;

    /**
     * @var MetricsAggregator
     */
    private $metricsAggregator;

    /**
     * @var EventId|null The ID of the last event captured within this context
     */
    private $lastEventId;

    /**
     * @internal
     */
    public function __construct(string $id, ?IsolationScope $isolationScope = null)
    {
        $this->id = $id;
        $this->isolationScope = $isolationScope ?? new IsolationScope();
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
    public function getIsolationScope(): IsolationScope
    {
        return $this->isolationScope;
    }

    /**
     * @internal
     */
    public function setIsolationScope(IsolationScope $isolationScope): void
    {
        $this->isolationScope = $isolationScope;
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

    /**
     * @internal
     */
    public function getLastEventId(): ?EventId
    {
        return $this->lastEventId;
    }

    /**
     * @internal
     */
    public function setLastEventId(?EventId $lastEventId): void
    {
        $this->lastEventId = $lastEventId;
    }
}
