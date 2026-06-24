<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 */
class MergedScope extends Scope
{
    /**
     * @var Span|null Set a Span on the Scope
     */
    private $span;

    public function __construct(ScopeData $scopeData, ?Span $span = null)
    {
        $this->scopeData = $scopeData;
        $this->span = $span;
    }

    /**
     * Returns the client bound to this scope.
     */
    public function getClient(): ClientInterface
    {
        return $this->scopeData->getClient();
    }

    /**
     * Applies the current context and fingerprint to the event. If the event has
     * already some breadcrumbs on it, the ones from this scope won't get merged.
     *
     * @param Event $event The event object that will be enriched with scope data
     */
    public function applyToEvent(Event $event, ?EventHint $hint = null, ?Options $options = null): ?Event
    {
        $event->setFingerprint(array_merge($event->getFingerprint(), $this->scopeData->getFingerprint()));

        if (empty($event->getBreadcrumbs())) {
            $event->setBreadcrumb($this->scopeData->getBreadcrumbs());
        }

        if ($this->scopeData->getLevel() !== null) {
            $event->setLevel($this->scopeData->getLevel());
        }

        if (!empty($this->scopeData->getTags())) {
            $event->setTags(array_merge($this->scopeData->getTags(), $event->getTags()));
        }

        if (!empty($this->scopeData->getFlags())) {
            $event->setContext('flags', [
                'values' => array_map(static function (array $flag) {
                    return [
                        'flag' => key($flag),
                        'result' => current($flag),
                    ];
                }, array_values($this->scopeData->getFlags())),
            ]);
        }

        if (!empty($this->scopeData->getExtra())) {
            $event->setExtra(array_merge($this->scopeData->getExtra(), $event->getExtra()));
        }

        $scopeUser = $this->scopeData->getUser();
        if ($scopeUser !== null) {
            $user = $event->getUser();

            if ($user === null) {
                $user = $scopeUser;
            } else {
                $user = (clone $scopeUser)->merge($user);
            }

            $event->setUser($user);
        }

        /**
         * Apply the trace context to errors if there is a Span on the Scope.
         * Else fallback to the external propagation context or to the
         * propagation context.
         * But do not override a trace context already present.
         */
        $externalPropagationContext = null;
        if ($this->span === null) {
            $externalPropagationContext = self::getExternalPropagationContext();
        }

        $traceContext = $this->span !== null
            ? $this->span->getTraceContext()
            : ($externalPropagationContext ?? $this->scopeData->getPropagationContext()->getTraceContext());

        if (!\array_key_exists('trace', $event->getContexts())) {
            $event->setContext('trace', $traceContext);
        }

        if ($this->span !== null) {
            // Apply the dynamic sampling context to errors if there is a Transaction on the Scope
            $transaction = $this->span->getTransaction();
            if ($transaction !== null) {
                $event->setSdkMetadata('dynamic_sampling_context', $transaction->getDynamicSamplingContext());
            }
        } elseif ($externalPropagationContext === null) {
            $dynamicSamplingContext = $this->scopeData->getPropagationContext()->getDynamicSamplingContext();
            if ($dynamicSamplingContext === null && $options !== null) {
                $dynamicSamplingContext = DynamicSamplingContext::fromOptions($options, $this);
            }
            $event->setSdkMetadata('dynamic_sampling_context', $dynamicSamplingContext);
        }

        foreach (array_merge($this->scopeData->getContexts(), $event->getContexts()) as $name => $data) {
            $event->setContext($name, $data);
        }

        // We create a empty `EventHint` instance to allow processors to always receive a `EventHint` instance even if there wasn't one
        if ($hint === null) {
            $hint = new EventHint();
        }

        if ($event->getType() === EventType::event() || $event->getType() === EventType::transaction()) {
            if (empty($event->getAttachments())) {
                $event->setAttachments($this->scopeData->getAttachments());
            }
        }

        foreach (array_merge(parent::$globalEventProcessors, $this->scopeData->getEventProcessors()) as $processor) {
            $event = $processor($event, $hint);

            if ($event === null) {
                return null;
            }

            if (!$event instanceof Event) {
                throw new \InvalidArgumentException(\sprintf('The event processor must return null or an instance of the %s class', Event::class));
            }
        }

        return $event;
    }

    /**
     * Returns the span that is on the scope.
     */
    public function getSpan(): ?Span
    {
        return $this->span;
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

    public function getPropagationContext(): PropagationContext
    {
        return $this->scopeData->getPropagationContext();
    }

    public function __clone()
    {
        $this->scopeData = clone $this->scopeData;
    }
}
