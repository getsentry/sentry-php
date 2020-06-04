<?php

declare(strict_types=1);

namespace Sentry\Tracing;

/**
 * Class SpanRecorder.
 */
final class SpanRecorder
{
    /**
     * @var int Maximum number of spans that should be stored
     */
    private $maxSpans;

    /**
     * @var Span[] Collection of Spans
     */
    private $spans = [];

    /**
     * SpanRecorder constructor.
     */
    public function __construct(int $maxSpans = 1000)
    {
        $this->maxSpans = $maxSpans;
    }

    /**
     * Adds a span to the array.
     */
    public function add(Span $span): void
    {
        if (\count($this->spans) > $this->maxSpans) {
            $span->spanRecorder = null;
        } else {
            array_push($this->spans, $span);
        }
    }

    /**
     * @return Span[]
     */
    public function getSpans(): array
    {
        return $this->spans;
    }
}
