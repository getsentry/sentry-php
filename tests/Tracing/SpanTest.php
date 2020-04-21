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
    public function testConstructor(): void
    {
        $context = new SpanContext();
        $context->traceId = TraceId::generate();
        $context->spanId = SpanId::generate();
        $context->parentSpanId = SpanId::generate();
        $context->description = 'description';
        $context->op = 'op';
        $context->status = 'ok';
        $context->sampled = true;
        $tags = [];
        $tags['a'] = 'b';
        $context->tags = $tags;
        $data = [];
        $data['c'] = 'd';
        $context->data = $data;
        $context->startTimestamp = microtime(true);
        $context->endTimestamp = microtime(true);

        $span = new Span($context);

        $this->assertEquals($context->traceId, $span->getTraceId());
        $this->assertEquals($context->spanId, $span->getSpanId());
        $this->assertEquals($context->parentSpanId, $span->getParentSpanId());
        $this->assertSame($context->op, $span->getOp());
        $this->assertSame($context->description, $span->getDescription());
        $this->assertSame($context->status, $span->getStatus());
        $this->assertSame($context->tags, $span->getTags());
        $this->assertSame($context->data, $span->getData());
        $this->assertSame($context->startTimestamp, $span->getStartTimestamp());
        $this->assertSame($context->endTimestamp, $span->getEndTimestamp());
    }

    /**
     * @dataProvider finishDataProvider
     */
    public function testFinish(?float $currentTimestamp, ?float $timestamp, float $expectedTimestamp): void
    {
        ClockMock::withClockMock($currentTimestamp);

        $span = new Span();
        $span->finish($timestamp);

        $this->assertSame($expectedTimestamp, $span->getEndTimestamp());
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

    public function testTraceparentHeader(): void
    {
        $context = SpanContext::fromTraceparent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb');
        $this->assertEquals('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $context->traceId);
        $this->assertNotEquals('bbbbbbbbbbbbbbbb', $context->spanId);
        $this->assertEquals('bbbbbbbbbbbbbbbb', $context->parentSpanId);
        $this->assertNull($context->sampled);
    }

    public function testTraceparentHeaderSampledTrue(): void
    {
        $context = SpanContext::fromTraceparent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-1');
        $this->assertTrue($context->sampled);
    }

    public function testTraceparentHeaderSampledFalse(): void
    {
        $context = SpanContext::fromTraceparent('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-0');
        $this->assertFalse($context->sampled);
    }

    public function testTraceparentHeaderJustSampleRate(): void
    {
        $context = SpanContext::fromTraceparent('1');
        $this->assertTrue($context->sampled);

        $context = SpanContext::fromTraceparent('0');
        $this->assertFalse($context->sampled);
    }
}
