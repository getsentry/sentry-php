<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;
use Sentry\Tracing\SpanStatus;

class Span
 {
    private $hub;

    public $startTimestamp;

    public $endTimestamp;

    public $exclusiveTime;

    public $op;

    public $description;

    public $status;

    public $data;

    public $traceId;

    public $segmentId;
    
    public $spanId;

    public $isSegment;

    private function __construct()
    {
        $this->hub = SentrySdk::getCurrentHub();

        $this->traceId = TraceId::generate();
        $this->segmentId = SegmentId::generate();
        $this->spanId = SpanId::generate();

        $this->isSegment = true;
    }

    public static function make(): self
    {
        return new self();
    }

    public function start(): self
    {
        $this->startTimestamp = microtime(true);

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


    public function finish(): ?EventId
    {
        $this->endTimestamp = microtime(true);
        $this->exclusiveTime = $this->endTimestamp - $this->startTimestamp;

        $this->status = (string) SpanStatus::ok();

        $event = Event::createSpan();
        $event->setSpan($this);

        return $this->hub->captureEvent($event);
    }
 }