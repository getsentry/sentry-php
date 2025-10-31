<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\Attributes\AttributeBag;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanStatus;

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
     * @var ?SpanId
     */
    private $parentSpanId;

    /**
     * @var ?SpanId
     */
    private $segmentSpanId;

    private $kind;

    /**
     * @var bool
     */
    private $isSegment;

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

    public function start(?Span $parentSpan = null): self
    {
        $this->startTimestamp = microtime(true);
        $client = $this->hub->getClient();

        /**
         * TODO: Do we assert that this is always the new span or do we fail?
         *
         * @var Span $parentSpan
         */
        $parentSpan = $parentSpan ?? $this->hub->getSpan();

        // If this is not a segment span
        if ($parentSpan !== null) {
            $this->parentSpanId = $parentSpan->getSpanId();
            $this->traceId = $parentSpan->getTraceId();
            $this->segmentSpanId = $parentSpan->segmentSpanId ?? $parentSpan->getSpanId();

        // Segment span
        } else {
            $pc = null;
            $this->hub->configureScope(function (Scope $scope) use (&$pc) {
                $pc = $scope->getPropagationContext();
            });
            // assert for linters that it's always not null at this point
            \assert($pc !== null);

            $this->traceId = new TraceId((string) $pc->getTraceId());

            if ($pc->getParentSpanId() !== null) {
                $this->setParentSpanId(new SpanId((string) $pc->getParentSpanId()));
                $this->setSegmentSpanId(new SpanId((string) $pc->getSpanId()));
            }

            $pc->setSpanId(new \Sentry\Tracing\SpanId((string) $this->getSpanId()));
            $this->hub->configureScope(function (Scope $scope) use ($pc) {
                $scope->setPropagationContext($pc);
            });

            $this->metadata['sampled_rand'] = round(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax(), 6);
            if ($client !== null) {
                $this->metadata['sampled'] = $this->metadata['sampled_rand'] < $client->getOptions()->getSampleRate();
            }
        }

        $this->hub->setSpan($this);

        Spans::getInstance()->add($this);

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

    public function setTraceId(TraceId $traceId): self
    {
        $this->traceId = $traceId;

        return $this;
    }

    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    public function applyFromParent(?Span $parentSpan): self
    {
        if ($parentSpan !== null) {
            if ($this->segmentSpanId === null) {
                $this->segmentSpanId = $parentSpan->segmentSpanId;
            }
            if ($this->parentSpanId === null) {
                $this->parentSpanId = $parentSpan->spanId;
            }
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): ?SpanStatus
    {
        return $this->status;
    }

    public function isSegment(): bool
    {
        return $this->segmentSpanId === null;
    }

    public function getSampled(): ?bool
    {
        return $this->getSegmentSpan()->metadata['sampled'] ?? null;
    }

    public function getSampleRand(): ?float
    {
        return $this->getSegmentSpan()->metadata['sampled_rand'] ?? null;
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

    public function getSegmentName(): string
    {
        return $this->getSegmentSpan()->getName();
    }

    public function getDynamicSamplingContext(): DynamicSamplingContext
    {
        return DynamicSamplingContext::fromSegment($this->getSegmentSpan(), $this->hub);
    }

    public function getSampleRate(): ?float
    {
        return $this->getSegmentSpan()->metadata['sample_rate'] ?? null;
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

        $parentSpan = Spans::getInstance()->get($this->parentSpanId);
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
        //
        // if ($this->op !== null) {
        //     $result['op'] = $this->op;
        // }
        //
        // if ($this->status !== null) {
        //     $result['status'] = (string) $this->status;
        // }
        //
        // if (!empty($this->data)) {
        //     $result['data'] = $this->data;
        // }
        //
        // if (!empty($this->tags)) {
        //     $result['tags'] = $this->tags;
        // }

        return $result;
    }

    public function toTraceparent(): string
    {
        $sampled = '';

        if ($this->getSampled() !== null) {
            $sampled = $this->getSampled() ? '-1' : '-0';
        }

        return \sprintf('%s-%s%s', (string) $this->traceId, (string) $this->spanId, $sampled);
    }

    public function setParentSpanId(SpanId $id): self
    {
        $this->parentSpanId = $id;

        return $this;
    }

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setSegmentSpanId(SpanId $id): self
    {
        $this->segmentSpanId = $id;

        return $this;
    }

    public function getSegmentSpanId(): ?SpanId
    {
        return $this->segmentSpanId;
    }

    /**
     * Returns the span that should be considered for metadata.
     *
     * For segment spans, it will return itself.
     * For child spans, it will find the relevant segment span.
     */
    private function getSegmentSpan(): Span
    {
        return Spans::getInstance()->get($this->segmentSpanId) ?? $this;
    }
}
