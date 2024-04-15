<?php

declare(strict_types=1);

namespace Sentry\Tracing\Spans;

class SegmentSpan extends AbstractSpan
 {
    public static function make(): self
    {
        return new self();
    }
 }