<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\Span;

/**
 * The scope holds data that should implicitly be sent with Sentry events. It
 * can hold context data, extra parameters, level overrides, fingerprints etc.
 *
 * @internal
 */
final class MergedScope extends Scope
{
    /**
     * @var Span|null
     */
    private $span;

    public function __construct(ScopeData $scopeData, ?Span $span)
    {
        $this->scopeData = $scopeData;
        $this->span = $span;
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
            $breadcrumbs = $this->scopeData->getBreadcrumbs();
            $maxBreadcrumbs = $options !== null ? $options->getMaxBreadcrumbs() : Options::DEFAULT_MAX_BREADCRUMBS;

            if (\count($breadcrumbs) > $maxBreadcrumbs) {
                $breadcrumbs = $maxBreadcrumbs > 0 ? \array_slice($breadcrumbs, -$maxBreadcrumbs) : [];
            }

            $event->setBreadcrumb($breadcrumbs);
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
            $propagationContext = $this->scopeData->getPropagationContext();
            $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();
            if ($dynamicSamplingContext === null && $options !== null) {
                $dynamicSamplingContext = DynamicSamplingContext::fromOptionsAndPropagationContext($options, $propagationContext);
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
}
