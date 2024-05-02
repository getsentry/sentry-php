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

    public $name;

    public $context = [];

    public $traceId;
    
    public $spanId;

    public $parentSpanId;

    public $kind;

    public $startTimeUnixNano;

    public $endTimeUnixNano;

    public $attributes = [];

    public $events = [];

    public $status;

    public $links = [];

    // Sentry specifics

    private $hub;

    public $segmentSpan;

    public $metadata = [];

    public function __construct()
    {
        $this->hub = SentrySdk::getCurrentHub();

        $this->traceId = TraceId::generate();
        $this->spanId = SpanId::generate();
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
        $this->startTimeUnixNano = microtime(true);

        $parentSpan = $this->hub->getSpan();
        if ($parentSpan !== null) {
            $this->parentSpanId = $parentSpan->spanId;
            $this->traceId = $parentSpan->traceId;

            $this->segmentSpan = $parentSpan->segmentSpan ?? $parentSpan;
        }


        $this->hub->setSpan($this);

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setStartTimeUnixNanosetStartTime(float $startTime): self
    {
        $this->startTimeUnixNano = $startTime;

        return $this;
    }

    public function setEndTimeUnixNanosetStartTime(float $endTime): self
    {
        $this->endTimeUnixNano = $endTime;

        return $this;
    }

    public function setAttribiute(string $key, $value): self
    {
        $this->attributes[] = [
            $key => $value,
        ];

        return $this;
    }

    public function finish(): ?EventId
    {
        if ($this->endTimeUnixNano !== null) {
            // The span was already finished once and we don't want to re-flush it
            return null;
        }

        $this->endTimeUnixNano = microtime(true);
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
        // 
        // if ($this->description !== null) {
        //     $result['description'] = $this->description;
        // }

        // if ($this->op !== null) {
        //     $result['op'] = $this->op;
        // }

        // if ($this->status !== null) {
        //     $result['status'] = (string) $this->status;
        // }

        // if (!empty($this->data)) {
        //     $result['data'] = $this->data;
        // }

        // if (!empty($this->tags)) {
        //     $result['tags'] = $this->tags;
        // }

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