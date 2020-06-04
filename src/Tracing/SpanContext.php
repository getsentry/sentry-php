<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Context\Context;
use Sentry\Context\TagsContext;

class SpanContext
{
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
     * @var TagsContext|null A List of tags associated to this Span
     */
    public $tags;

    /**
     * @var Context<mixed>|null An arbitrary mapping of additional metadata
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
}
