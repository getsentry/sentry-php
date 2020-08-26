<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Context\Context;
use Sentry\Context\TagsContext;
use Sentry\EventId;

/**
 * This class stores all the information about a Span.
 */
class Span implements \JsonSerializable
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
     * @var TagsContext A List of tags associated to this Span
     */
    protected $tags;

    /**
     * @var Context<mixed> An arbitrary mapping of additional metadata
     */
    protected $data;

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

        if ($context && $context->tags) {
            $this->tags = new TagsContext($context->tags);
        } else {
            $this->tags = new TagsContext();
        }

        if ($context && $context->data) {
            $this->data = new Context($context->data);
        } else {
            $this->data = new Context();
        }

        $this->startTimestamp = $context->startTimestamp ?? microtime(true);
        $this->endTimestamp = $context->endTimestamp ?? null;
    }

    /**
     * Sets the finish timestamp on the current span.
     *
     * @param float|null $endTimestamp Takes an endTimestamp if the end should not be the time when you call this function
     *
     * @return EventId|null Finish for a span always returns null
     */
    public function finish($endTimestamp = null): ?EventId
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

    public function getStartTimestamp(): float
    {
        return $this->startTimestamp;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOp(): ?string
    {
        return $this->op;
    }

    public function setOp(?string $op): void
    {
        $this->op = $op;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getTags(): TagsContext
    {
        return $this->tags;
    }

    /**
     * @param array<string, string> $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags->merge($tags);
    }

    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    public function setSpanId(SpanId $spanId): void
    {
        $this->spanId = $spanId;
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

    public function getSampled(): ?bool
    {
        return $this->sampled;
    }

    public function setSampled(?bool $sampled): void
    {
        $this->sampled = $sampled;
    }

    /**
     * @param array<mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data->merge($data);
    }

    public function setStartTimestamp(float $startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }
}
