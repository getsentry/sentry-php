<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class SpanTest extends TestCase
{
    /**
     * @dataProvider finishDataProvider
     */
    public function testFinish(?float $currentTimestamp, ?float $endTimestamp, float $expectedEndTimestamp): void
    {
        ClockMock::withClockMock($currentTimestamp);

        $span = new Span();
        $span->finish($endTimestamp);

        $this->assertSame($expectedEndTimestamp, $span->getEndTimestamp());
    }

    public function finishDataProvider(): iterable
    {
        yield [
            1598660006,
            null,
            1598660006,
        ];

        yield [
            1598660006,
            1598660332,
            1598660332,
        ];
    }

    public function testStartChild(): void
    {
        $spanContext2ParentSpanId = SpanId::generate();
        $spanContext2TraceId = TraceId::generate();

        $spanContext1 = new SpanContext();
        $spanContext1->setSampled(false);
        $spanContext1->setSpanId(SpanId::generate());
        $spanContext1->setTraceId(TraceId::generate());

        $spanContext2 = new SpanContext();
        $spanContext2->setSampled(true);
        $spanContext2->setParentSpanId($spanContext2ParentSpanId);
        $spanContext2->setTraceId($spanContext2TraceId);

        $span1 = new Span($spanContext1);
        $span2 = $span1->startChild($spanContext2);

        $this->assertSame($spanContext1->getSampled(), $span1->getSampled());
        $this->assertSame($spanContext1->getSpanId(), $span1->getSpanId());
        $this->assertSame($spanContext1->getTraceId(), $span1->getTraceId());

        $this->assertSame($spanContext1->getSampled(), $span2->getSampled());
        $this->assertSame($spanContext1->getSpanId(), $span2->getParentSpanId());
        $this->assertSame($spanContext1->getTraceId(), $span2->getTraceId());
    }
}
