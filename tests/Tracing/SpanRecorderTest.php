<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanRecorder;

final class SpanRecorderTest extends TestCase
{
    public function testAdd(): void
    {
        $span1 = new class extends Span {
            public function __construct()
            {
                parent::__construct();

                $this->spanRecorder = new SpanRecorder(1);
                $this->spanRecorder->add($this);
            }

            public function getSpanRecorder(): SpanRecorder
            {
                return $this->spanRecorder;
            }
        };

        $span2 = $span1->startChild(new SpanContext());
        $span3 = $span2->startChild(new SpanContext()); // this should not end up being recorded

        $this->assertSame([$span1, $span2], $span1->getSpanRecorder()->getSpans());
    }
}
