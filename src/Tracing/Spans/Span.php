<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanStatus;

class Span
 {
    private const SENTRY_TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    private $hub;

    public $traceId;

    public $segmentId;
    
    public $spanId;

    public $parentSpanId;

    public $isSegment;

    public $startTimestamp;

    public $endTimestamp;

    public $exclusiveTime;

    public $op;

    public $description;

    public $status;

    public $tags = [];

    public $data = [];

    public $context = [];

    public $metadata = [];

    public $metricsSummary = [];

    public function __construct()
    {
        $this->hub = SentrySdk::getCurrentHub();

        $this->traceId = TraceId::generate();
        // $this->segmentId = SegmentId::generate();
        $this->spanId = SpanId::generate();
        $this->segmentId = $this->spanId;
    }

    public static function make(): self
    {
        return new self();
    }

    public static function makeFromTrace(string $sentryTrace, string $baggage): self
    {
        $span = new self();

        self::parseTraceAndBaggage($span, $sentryTrace, $baggage);

        return $span;
    }

    public function start(): self
    {
        $this->startTimestamp = microtime(true);

        $parentSpan = $this->hub->getSpan();
        if ($parentSpan !== null) {
            $this->parentSpanId = $parentSpan->spanId;
            $this->traceId = $parentSpan->traceId;
            $this->segmentId = $parentSpan->segmentId;
            $this->isSegment = false;
        } else {
            $this->isSegment = true;
        }


        $this->hub->setSpan($this);

        return $this;
    }

    public function setOp(string $op): self
    {
        $this->op = $op;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    public function setData(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function setContext(array $context)
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function setStartTimestamp(?float $startTimestamp): self
    {
        $this->startTimestamp = $startTimestamp;

        return $this;
    }

    public function setEndTimestamp(?float $endTimestamp): self
    {
        $this->endTimestamp = $endTimestamp;

        return $this;
    }

    public function finish(): ?EventId
    {
        if ($this->endTimestamp !== null) {
            // The span was already finished once and we don't want to re-flush it
            return null;
        }

        $this->endTimestamp = microtime(true);
        $this->exclusiveTime = $this->endTimestamp - $this->startTimestamp;

        $this->status = (string) SpanStatus::ok();

        $event = Event::createSpan();
        $event->setSpan($this);

        return $this->hub->captureEvent($event);
    }

    public function getTraceContext(): array
    {
        $result = [
            'span_id' => (string) $this->spanId,
            'trace_id' => (string) $this->traceId,
        ];

        if ($this->parentSpanId !== null) {
            $result['parent_span_id'] = (string) $this->parentSpanId;
        }

        // TBD do we need all this data on the trace context?

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->op !== null) {
            $result['op'] = $this->op;
        }

        if ($this->status !== null) {
            $result['status'] = (string) $this->status;
        }

        if (!empty($this->data)) {
            $result['data'] = $this->data;
        }

        if (!empty($this->tags)) {
            $result['tags'] = $this->tags;
        }

        return $result;
    }

    private static function parseTraceAndBaggage(Span $span, string $sentryTrace, string $baggage)
    {
        $hasSentryTrace = false;

        if (preg_match(self::SENTRY_TRACEPARENT_HEADER_REGEX, $sentryTrace, $matches)) {
            if (!empty($matches['trace_id'])) {
                $span->traceId = new TraceId($matches['trace_id']);
                $hasSentryTrace = true;
            }

            if (!empty($matches['span_id'])) {
                $span->parentSpanId = new SpanId($matches['span_id']);
                $hasSentryTrace = true;
            }
        }

        $samplingContext = DynamicSamplingContext::fromHeader($baggage);

        if ($hasSentryTrace && !$samplingContext->hasEntries()) {
            // The request comes from an old SDK which does not support Dynamic Sampling.
            // Propagate the Dynamic Sampling Context as is, but frozen, even without sentry-* entries.
            $samplingContext->freeze();
            $span->metadata['dynamic_sampling_context'] = $samplingContext;
        }

        if ($hasSentryTrace && $samplingContext->hasEntries()) {
            // The baggage header contains Dynamic Sampling Context data from an upstream SDK.
            // Propagate this Dynamic Sampling Context.
            $span->metadata['dynamic_sampling_context'] = $samplingContext;
        }
    }
 }