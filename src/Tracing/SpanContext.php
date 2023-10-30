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

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getOp(): ?string
    {
        return $this->op;
    }

    public function setOp(?string $op): self
    {
        $this->op = $op;

        return $this;
    }

    public function getStatus(): ?SpanStatus
    {
        return $this->status;
    }

    public function setStatus(?SpanStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setParentSpanId(?SpanId $parentSpanId): self
    {
        $this->parentSpanId = $parentSpanId;

        return $this;
    }

    public function getSampled(): ?bool
    {
        return $this->sampled;
    }

    public function setSampled(?bool $sampled): self
    {
        $this->sampled = $sampled;

        return $this;
    }

    public function getSpanId(): ?SpanId
    {
        return $this->spanId;
    }

    public function setSpanId(?SpanId $spanId): self
    {
        $this->spanId = $spanId;

        return $this;
    }

    public function getTraceId(): ?TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(?TraceId $traceId): self
    {
        $this->traceId = $traceId;

        return $this;
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
    public function setTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
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
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getStartTimestamp(): ?float
    {
        return $this->startTimestamp;
    }

    public function setStartTimestamp(?float $startTimestamp): self
    {
        $this->startTimestamp = $startTimestamp;

        return $this;
    }

    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }

    public function setEndTimestamp(?float $endTimestamp): self
    {
        $this->endTimestamp = $endTimestamp;

        return $this;
    }
}
