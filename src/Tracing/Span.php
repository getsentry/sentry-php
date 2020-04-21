<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\EventId;

/**
 * This class stores all the information about a Span.
 */
class Span
{
    /**
     * @var SpanId Span ID
     */
    protected $spanId;

    /**
     * @var TraceId Trace ID
     */
    protected $traceId;

    /**
     * @var string|null Description of the Span
     */
    protected $description;

    /**
     * @var string|null Operation of the Span
     */
    protected $op;

    /**
     * @var string|null Completion status of the Span
     */
    protected $status;

    /**
     * @var SpanId|null ID of the parent Span
     */
    protected $parentSpanId;

    /**
     * @var bool|null Has the sample decision been made?
     */
    protected $sampled;

    /**
     * @var array<string, string> A List of tags associated to this Span
     */
    protected $tags = [];

    /**
     * @var array<string, mixed> An arbitrary mapping of additional metadata
     */
    protected $data = [];

    /**
     * @var float Timestamp in seconds (epoch time) indicating when the span started
     */
    protected $startTimestamp;

    /**
     * @var float|null Timestamp in seconds (epoch time) indicating when the span ended
     */
    protected $endTimestamp;

    /**
     * @var SpanRecorder|null Reference instance to the SpanRecorder
     *
     * @internal
     */
    public $spanRecorder;

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

        if (null !== $context && $context->tags) {
            $this->tags = $context->tags;
        }

        if (null !== $context && $context->data) {
            $this->data = $context->data;
        }

        $this->startTimestamp = $context->startTimestamp ?? microtime(true);
        $this->endTimestamp = $context->endTimestamp ?? null;
    }

    /**
     * Sets the ID of the span.
     *
     * @param SpanId $spanId The ID
     */
    public function setSpanId(SpanId $spanId): void
    {
        $this->spanId = $spanId;
    }

    /**
     * Gets the ID that determines which trace the span belongs to.
     */
    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    /**
     * Sets the ID that determines which trace the span belongs to.
     *
     * @param TraceId $traceId The ID
     */
    public function setTraceId(TraceId $traceId): void
    {
        $this->traceId = $traceId;
    }

    /**
     * Gets the ID that determines which span is the parent of the current one.
     */
    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    /**
     * Sets the ID that determines which span is the parent of the current one.
     *
     * @param SpanId|null $parentSpanId The ID
     */
    public function setParentSpanId(?SpanId $parentSpanId): void
    {
        $this->parentSpanId = $parentSpanId;
    }

    /**
     * Gets the timestamp representing when the measuring started.
     */
    public function getStartTimestamp(): float
    {
        return $this->startTimestamp;
    }

    /**
     * Sets the timestamp representing when the measuring started.
     *
     * @param float $startTimestamp The timestamp
     */
    public function setStartTimestamp(float $startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    /**
     * Gets the timestamp representing when the measuring finished.
     */
    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }

    /**
     * Returns `sentry-trace` header content.
     */
    public function toTraceparent(): string
    {
        $sampled = '';
        if (null !== $this->sampled) {
            $sampled = $this->sampled ? '-1' : '-0';
        }

        return $this->traceId . '-' . $this->spanId . $sampled;
    }

    /**
     * Gets a description of the span's operation, which uniquely identifies
     * the span but is consistent across instances of the span.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Sets a description of the span's operation, which uniquely identifies
     * the span but is consistent across instances of the span.
     *
     * @param string|null $description The description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * Gets a short code identifying the type of operation the span is measuring.
     */
    public function getOp(): ?string
    {
        return $this->op;
    }

    /**
     * Sets a short code identifying the type of operation the span is measuring.
     *
     * @param string|null $op The short code
     */
    public function setOp(?string $op): void
    {
        $this->op = $op;
    }

    /**
     * Gets the status of the span/transaction.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Sets the status of the span/transaction.
     *
     * @param string|null $status The status
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * Gets a map of tags for this event.
     *
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Sets a map of tags for this event.
     *
     * @param array<string, string> $tags The tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }

    /**
     * Gets the ID of the span.
     */
    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    /**
     * Gets the flag determining whether this span should be sampled or not.
     */
    public function getSampled(): ?bool
    {
        return $this->sampled;
    }

    /**
     * Sets the flag determining whether this span should be sampled or not.
     *
     * @param bool $sampled Whether to sample or not this span
     */
    public function setSampled(?bool $sampled): void
    {
        $this->sampled = $sampled;
    }

    /**
     * Gets a map of arbitrary data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets a map of arbitrary data. This method will merge the given data with
     * the existing one.
     *
     * @param array<string, mixed> $data The data
     */
    public function setData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Sets the finish timestamp on the current span.
     *
     * @param float|null $endTimestamp Takes an endTimestamp if the end should not be the time when you call this function
     *
     * @return EventId|null Finish for a span always returns null
     */
    public function finish(?float $endTimestamp = null): ?EventId
    {
        $this->endTimestamp = $endTimestamp ?? microtime(true);

        return null;
    }

    /**
     * Creates a new `Span` while setting the current `Span.id` as `parentSpanId`.
     * Also the `sampled` decision will be inherited.
     *
     * @param SpanContext $context The Context of the child span
     *
     * @return Span Instance of the newly created Span
     */
    public function startChild(SpanContext $context): self
    {
        $context->sampled = $this->sampled;
        $context->parentSpanId = $this->spanId;
        $context->traceId = $this->traceId;

        $span = new self($context);

        $span->spanRecorder = $this->spanRecorder;
        if (null != $span->spanRecorder) {
            $span->spanRecorder->add($span);
        }

        return $span;
    }
}
