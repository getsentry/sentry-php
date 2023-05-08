<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

final class PropagationContext
{
    /**
     * @var TraceId The trace id
     */
    private $traceId;

    /**
     * @var SpanId The span id
     */
    private $spanId;

    /**
     * @var DynamicSamplingContext|null The dynamic sampling context
     */
    private $dynamicSamplingContext;

    public function __construct()
    {
        $this->traceId = TraceId::generate();
        $this->spanId = SpanId::generate();
        $this->dynamicSamplingContext = null;
    }

    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(TraceId $traceId): void
    {
        $this->traceId = $traceId;
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
}
