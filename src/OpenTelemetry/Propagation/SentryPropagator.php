<?php

declare(strict_types=1);

namespace Sentry\OpenTelemetry\Propagation;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

class SentryPropagator implements TextMapPropagatorInterface
{
    public const SENTRY_TRACE = 'sentry-trace';

    /**
     * @var self|null
     */
    private static $instance;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function fields(): array
    {
        return [self::SENTRY_TRACE];
    }

    /**
     * @param mixed $carrier
     */
    public function inject(&$carrier, ?PropagationSetterInterface $setter = null, ?ContextInterface $context = null): void
    {
        if ($setter === null) {
            $setter = ArrayAccessGetterSetter::getInstance();
        }

        if ($context === null) {
            $context = Context::getCurrent();
        }

        $spanContext = Span::fromContext($context)->getContext();

        if (!$spanContext->isValid()) {
            return;
        }

        $sampled = $spanContext->isSampled() ? '1' : '0';
        $sentryTrace = \sprintf('%s-%s-%s', $spanContext->getTraceId(), $spanContext->getSpanId(), $sampled);

        $setter->set($carrier, self::SENTRY_TRACE, $sentryTrace);
    }

    /**
     * @param mixed $carrier
     */
    public function extract($carrier, ?PropagationGetterInterface $getter = null, ?ContextInterface $context = null): ContextInterface
    {
        if ($getter === null) {
            $getter = ArrayAccessGetterSetter::getInstance();
        }

        if ($context === null) {
            $context = Context::getCurrent();
        }

        // Traceparent header has higher precedence over sentry-trace header if traceparent propagator is enabled.
        if (!empty($getter->get($carrier, TraceContextPropagator::TRACEPARENT)) && $this->isTraceparentPropagatorEnabled()) {
            return $context;
        }

        $sentryTrace = $getter->get($carrier, self::SENTRY_TRACE);
        if ($sentryTrace === null) {
            return $context;
        }

        // Format: sentry-trace = {trace-id}-{span-id}-{sampled flag (optional)}.
        $parts = explode('-', $sentryTrace);

        // If the header does not have at least 2 parts, it is invalid.
        if (\count($parts) < 2) {
            return $context;
        }

        [$traceId, $spanId] = $parts;
        $traceFlags = isset($parts[2]) && $parts[2] === '1' ? TraceFlags::SAMPLED : TraceFlags::DEFAULT;
        $spanContext = SpanContext::createFromRemoteParent($traceId, $spanId, $traceFlags);

        if (!$spanContext->isValid()) {
            return $context;
        }

        return $context->withContextValue(Span::wrap($spanContext));
    }

    private function isTraceparentPropagatorEnabled(): bool
    {
        return \in_array(TraceContextPropagator::TRACEPARENT, Globals::propagator()->fields());
    }
}
