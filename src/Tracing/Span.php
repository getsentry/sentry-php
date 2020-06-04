<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Context\Context;
use Sentry\Context\TagsContext;

/**
 * This class stores all the information about a Span.
 */
class Span implements \JsonSerializable
{
    /**
     * @var SpanId Span ID
     */
    private $spanId;

    /**
     * @var TraceId Trace ID
     */
    private $traceId;

    /**
     * @var string|null Description of the Span
     */
    private $description;

    /**
     * @var string|null Operation of the Span
     */
    private $op;

    /**
     * @var string|null Completion status of the Span
     */
    private $status;

    /**
     * @var SpanId|null ID of the parent Span
     */
    private $parentSpanId;

    /**
     * @var bool|null Has the sample decision been made?
     */
    private $sampled;

    /**
     * @var TagsContext A List of tags associated to this Span
     */
    private $tags;

    /**
     * @var Context<mixed> An arbitrary mapping of additional metadata
     */
    private $data;

    /**
     * @var float Timestamp in seconds (epoch time) indicating when the span started
     */
    private $startTimestamp;

    /**
     * @var float|null Timestamp in seconds (epoch time) indicating when the span ended
     */
    private $endTimestamp;

    /**
     * Span constructor.
     *
     * @param SpanContext|null $context The context to create the span with
     *
     * @internal
     */
    public function __construct(?SpanContext $context = null)
    {
        $this->traceId = $context->traceId ?? TraceId::generate();
        $this->spanId = $context->spanId ?? SpanId::generate();
        $this->parentSpanId = $context->parentSpanId ?? null;
        $this->description = $context->description ?? null;
        $this->op = $context->op ?? null;
        $this->status = $context->status ?? null;
        $this->sampled = $context->sampled ?? null;
        $this->tags = $context->tags ?? new TagsContext();
        $this->data = $context->data ?? new Context();
        $this->startTimestamp = $context->startTimestamp ?? microtime(true);
        $this->endTimestamp = $context->endTimestamp ?? null;
    }

    /**
     * Sets the finish timestamp on the current span.
     *
     * @param float|null $endTimestamp Takes an endTimestamp if the end should not be the time when you call this function
     */
    public function finish($endTimestamp = null): void
    {
        $this->endTimestamp = $endTimestamp ?? microtime(true);
    }

    /**
     * Gets the event as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'span_id' => (string) $this->spanId,
            'trace_id' => (string) $this->traceId,
            'start_timestamp' => $this->startTimestamp,
        ];

        if (null !== $this->parentSpanId) {
            $data['parent_span_id'] = (string) $this->parentSpanId;
        }

        if (null !== $this->endTimestamp) {
            $data['timestamp'] = $this->endTimestamp;
        }

        if (null !== $this->status) {
            $data['status'] = $this->status;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->op) {
            $data['op'] = $this->op;
        }

        if (!$this->data->isEmpty()) {
            $data['data'] = $this->data->toArray();
        }

        if (!$this->tags->isEmpty()) {
            $data['tags'] = $this->tags->toArray();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
