<?php

declare(strict_types=1);

namespace Sentry\Tracing;

class SpanContext
{
    /**
     * @var string|null Description of the Span
     */
    private $description;

    /**
     * @var string|null Operation of the Span
     */
    private $op;

    /**
     * @var SpanStatus|null Completion status of the Span
     */
    private $status;

    /**
     * @var SpanId|null ID of the parent Span
     */
    protected $parentSpanId;

    /**
     * @var bool|null Has the sample decision been made?
     */
    private $sampled;

    /**
     * @var SpanId|null Span ID
     */
    private $spanId;

    /**
     * @var TraceId|null Trace ID
     */
    protected $traceId;

    /**
     * @var array<string, string> A List of tags associated to this Span
     */
    private $tags = [];

    /**
     * @var array<string, mixed> An arbitrary mapping of additional metadata
     */
    private $data = [];

    /**
     * @var float|null Timestamp in seconds (epoch time) indicating when the span started
     */
    private $startTimestamp;

    /**
     * @var float|null Timestamp in seconds (epoch time) indicating when the span ended
     */
    private $endTimestamp;

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

    public function getStatus(): ?SpanStatus
    {
        return $this->status;
    }

    public function setStatus(?SpanStatus $status): void
    {
        $this->status = $status;
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

    public function getSpanId(): ?SpanId
    {
        return $this->spanId;
    }

    public function setSpanId(?SpanId $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function getTraceId(): ?TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(?TraceId $traceId): void
    {
        $this->traceId = $traceId;
    }

    /**
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<string, string> $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getStartTimestamp(): ?float
    {
        return $this->startTimestamp;
    }

    public function setStartTimestamp(?float $startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }

    public function setEndTimestamp(?float $endTimestamp): void
    {
        $this->endTimestamp = $endTimestamp;
    }
}
