<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\Attributes\AttributeBag;
use Sentry\SentrySdk;

use Sentry\Tracing\SpanStatus;
use function Sentry\tracing;

class Span
{
    /**
     * @var string
     */
    private $name;

    /**
     * Even though we only allow 'ok' and 'error' as status here, we can re-use the old SpanStatus for now since
     * it has one 'ok' state and many 'error' states, which we can collapse into one.
     *
     * @var SpanStatus
     */
    private $status;

    /**
     * @var TraceId
     */
    private $traceId;

    /**
     * @var SpanId
     */
    private $spanId;

    /**
     * @var SpanId|null
     */
    private $parentSpanId;

    private $kind;

    private $isRemote;

    /**
     * @var float|null
     */
    private $startTimestamp;

    /**
     * @var float|null
     */
    private $endTimestamp;

    /**
     * @var AttributeBag
     */
    private $attributes;

    private $links = [];

    /**
     * @var \Sentry\State\HubInterface
     */
    private $hub;

    private $metadata = [];

    public function __construct()
    {
        $this->hub = SentrySdk::getCurrentHub();

        $this->traceId = TraceId::generate();
        $this->spanId = SpanId::generate();

        $this->attributes = new AttributeBag();
    }

    public static function make(): self
    {
        return new self();
    }

    public function start(): self
    {
        $this->startTimestamp = microtime(true);

        $parentSpan = $this->hub->getSpan();
        if ($parentSpan !== null) {
            $this->parentSpanId = $parentSpan->spanId;
            $this->traceId = $parentSpan->traceId;
        }

        $this->hub->setSpan($this);

        tracing()->add($this);

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setStartTimestamp(float $startTime): self
    {
        $this->startTimestamp = $startTime;

        return $this;
    }

    public function getStartTimestamp(): ?float
    {
        return $this->startTimestamp;
    }

    public function setEndTimestamp(float $endTime): self
    {
        $this->endTimestamp = $endTime;

        return $this;
    }

    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }

    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setParentSpanId(?SpanId $parentSpanId): void
    {
        $this->parentSpanId = $parentSpanId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): SpanStatus
    {
        return $this->status;
    }

    public function attributes(): AttributeBag
    {
        return $this->attributes;
    }

    /**
     * @param mixed $value
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes->set($key, $value);

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes->set($key, $value);
        }

        return $this;
    }

    public function setStatus(SpanStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    // TODO: Should this be called end?
    public function finish(): void
    {
        if ($this->endTimestamp !== null) {
            // The span was already finished once
            return;
        }

        $this->endTimestamp = microtime(true);
        $this->status = SpanStatus::ok();

        $parentSpan = $this->hub->getSpan();
        if ($parentSpan !== null) {
            $this->hub->setSpan($parentSpan);
        }
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

        // @TODO(michi) do we need all this data on the trace context?
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
}
