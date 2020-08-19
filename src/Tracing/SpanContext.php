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
