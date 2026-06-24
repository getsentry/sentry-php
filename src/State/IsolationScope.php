<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Event;
use Sentry\EventId;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
class IsolationScope extends MutableScope
{
    /**
     * @var Span|null Set a Span on the Scope
     */
    private $span;

    /**
     * @var EventId|null The ID of the last captured event
     */
    private $lastEventId;

    public function __construct(?PropagationContext $propagationContext = null)
    {
        parent::__construct();
        $this->scopeData->setPropagationContext($propagationContext ?? PropagationContext::fromDefaults());
    }

    /**
     * Returns the ID of the last captured event.
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

    /**
     * Adds a feature flag to the scope.
     *
     * @return $this
     */
    public function addFeatureFlag(string $key, bool $result): self
    {
        $this->scopeData->addFeatureFlag($key, $result);

        if ($this->span !== null) {
            $this->span->setFlag($key, $result);
        }

        return $this;
    }

    /**
     * Clears event payload data from the scope. The client binding and last
     * event ID are preserved.
     */
    public function clear(): void
    {
        parent::clear();
        $this->span = null;
    }

    /**
     * Returns the span that is on the scope.
     */
    public function getSpan(): ?Span
    {
        return $this->span;
    }

    /**
     * Sets the span on the scope.
     *
     * @param Span|null $span The span
     *
     * @return $this
     */
    public function setSpan(?Span $span): self
    {
        $this->span = $span;

        return $this;
    }

    /**
     * Returns the transaction attached to the scope (if there is one).
     */
    public function getTransaction(): ?Transaction
    {
        if ($this->span !== null) {
            return $this->span->getTransaction();
        }

        return null;
    }

    public function hasExternalPropagationContext(): bool
    {
        return $this->span === null && self::getExternalPropagationContext() !== null;
    }

    public function getPropagationContext(): PropagationContext
    {
        return $this->scopeData->getPropagationContext();
    }

    public function setPropagationContext(PropagationContext $propagationContext): self
    {
        $this->scopeData->setPropagationContext($propagationContext);

        return $this;
    }

    /**
     * @return array{
     *     trace_id: string,
     *     span_id: string,
     *     parent_span_id?: string,
     *     data?: array<string, mixed>,
     *     description?: string,
     *     op?: string,
     *     status?: string,
     *     tags?: array<string, string>,
     *     origin?: string
     * }
     */
    public function getTraceContext(): array
    {
        if ($this->span !== null) {
            return $this->span->getTraceContext();
        }

        return self::getExternalPropagationContext() ?? $this->scopeData->getPropagationContext()->getTraceContext();
    }
}
