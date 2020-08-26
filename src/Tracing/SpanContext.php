<?php

declare(strict_types=1);

namespace Sentry\Tracing;

class SpanContext
{
    private const TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    /**
     * @var string|null Description of the Span
     */
    public $description;

    /**
     * @var string|null Operation of the Span
     */
    public $op;

    /**
     * @var string|null Completion status of the Span
     */
    public $status;

    /**
     * @var SpanId|null ID of the parent Span
     */
    public $parentSpanId;

    /**
     * @var bool|null Has the sample decision been made?
     */
    public $sampled;

    /**
     * @var SpanId|null Span ID
     */
    public $spanId;

    /**
     * @var TraceId|null Trace ID
     */
    public $traceId;

    /**
     * @var array<string, string>|null A List of tags associated to this Span
     */
    public $tags;

    /**
     * @var array<string, mixed>|null An arbitrary mapping of additional metadata
     */
    public $data;

    /**
     * @var float|null Timestamp in seconds (epoch time) indicating when the span started
     */
    public $startTimestamp;

    /**
     * @var float|null Timestamp in seconds (epoch time) indicating when the span ended
     */
    public $endTimestamp;

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
    public function getTags(): ?array
    {
        return $this->tags;
    }

    /**
     * @param array<string, string>|null $tags
     */
    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function setData(?array $data): void
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

    /**
     * Returns a context depending on the header given. Containing trace_id, parent_span_id and sampled.
     *
     * @param string $header The sentry-trace header from the request
     *
     * @return static
     */
    public static function fromTraceparent(string $header)
    {
        /** @phpstan-ignore-next-line */ /** @psalm-suppress UnsafeInstantiation */
        $context = new static();

        if (!preg_match(self::TRACEPARENT_HEADER_REGEX, $header, $matches)) {
            return $context;
        }

        if (mb_strlen($matches['trace_id']) > 0) {
            $context->traceId = new TraceId($matches['trace_id']);
        }

        if (mb_strlen($matches['span_id']) > 0) {
            $context->parentSpanId = new SpanId($matches['span_id']);
        }

        if (\array_key_exists('sampled', $matches)) {
            $context->sampled = '1' === $matches['sampled'];
        }

        return $context;
    }
}
