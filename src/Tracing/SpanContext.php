<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\Context\Context;
use Sentry\Context\TagsContext;

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

    public static function fromTraceparent(string $header): self {
        $context = new SpanContext();
        if (!preg_match(self::TRACEPARENT_HEADER_REGEX, $header, $matches)) {
            return $context;
        }

        $context->traceId = $matches['trace_id'];
        $context->parentSpanId = $matches['span_id'];
        if (key_exists('sampled', $matches)) {
            $context->sampled = $matches['sampled'] === '1';
        }

        return $context;
    }
}
