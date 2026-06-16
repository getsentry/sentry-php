<?php

namespace Sentry\Tracing;

/**
 * Used to pass state between the callbacks registered in \Sentry\setStartCallback
 * and \Sentry\setEndCallback.
 */
final class FunctionTracingState
{
    /**
     * @var Span
     */
    private $parentSpan;

    /**
     * @var Span
     */
    private $currentSpan;

    public function __construct(Span $parentSpan, Span $currentSpan)
    {
        $this->parentSpan = $parentSpan;
        $this->currentSpan = $currentSpan;
    }

    /**
     * @return Span
     */
    public function getParentSpan(): Span
    {
        return $this->parentSpan;
    }

    /**
     * @return Span
     */
    public function getCurrentSpan(): Span
    {
        return $this->currentSpan;
    }
}
