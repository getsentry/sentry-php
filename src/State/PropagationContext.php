<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

final class PropagationContext
{
    // TODO(michi) Moce into central place
    private const TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    /**
     * @var TraceId The trace id
     */
    private $traceId;

    /**
     * @var SpanId The span id
     */
    private $spanId;

    /**
     * @var SpanId|null The parent span id
     */
    private $parentSpanId;

    /**
     * @var DynamicSamplingContext|null The dynamic sampling context
     */
    private $dynamicSamplingContext;

    public static function fromDefaults(): self
    {
        $context = new self();

        $context->traceId = TraceId::generate();
        $context->spanId = SpanId::generate();
        $context->parentSpanId = null;
        $context->dynamicSamplingContext = null;

        return $context;
    }

    public static function fromHeaders(string $sentryTraceHeader, string $baggageHeader): self
    {
        return self::parseTraceAndBaggage($sentryTraceHeader, $baggageHeader);
    }

    public static function fromEnvironment(string $sentryTrace, string $baggage): self
    {
        return self::parseTraceAndBaggage($sentryTrace, $baggage);
    }

    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(TraceId $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setParentSpanId(?SpanId $parentSpanId): void
    {
        $this->parentSpanId = $parentSpanId;
    }

    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    public function setSpanId(SpanId $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function getDynamicSamplingContext(): ?DynamicSamplingContext
    {
        return $this->dynamicSamplingContext;
    }

    public function setDynamicSamplingContext(DynamicSamplingContext $dynamicSamplingContext): void
    {
        $this->dynamicSamplingContext = $dynamicSamplingContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTraceContext(): array
    {
        $result = [
            'trace_id' => (string) $this->traceId,
            'span_id' => (string) $this->spanId,
        ];

        if (null !== $this->parentSpanId) {
            $result['parent_span_id'] = (string) $this->parentSpanId;
        }

        return $result;
    }

    private static function parseTraceAndBaggage(string $sentryTrace, string $baggage): self
    {
        $context = new self();
        $hasSentryTrace = false;

        if (preg_match(self::TRACEPARENT_HEADER_REGEX, $sentryTrace, $matches)) {
            if (!empty($matches['trace_id'])) {
                $context->traceId = new TraceId($matches['trace_id']);
                $hasSentryTrace = true;
            }

            if (!empty($matches['span_id'])) {
                $context->parentSpanId = new SpanId($matches['span_id']);
                $hasSentryTrace = true;
            }
        }

        $samplingContext = DynamicSamplingContext::fromHeader($baggage);

        if ($hasSentryTrace && !$samplingContext->hasEntries()) {
            // The request comes from an old SDK which does not support Dynamic Sampling.
            // Propagate the Dynamic Sampling Context as is, but frozen, even without sentry-* entries.
            $samplingContext->freeze();
            $context->dynamicSamplingContext = $samplingContext;
        }

        if ($hasSentryTrace && $samplingContext->hasEntries()) {
            // The baggage header contains Dynamic Sampling Context data from an upstream SDK.
            // Propagate this Dynamic Sampling Context.
            $context->dynamicSamplingContext = $samplingContext;
        }

        return $context;
    }
}
