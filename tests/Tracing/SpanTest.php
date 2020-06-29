<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

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
        $data = $span->jsonSerialize();

        $this->assertEquals($context->op, $data['op']);
        $this->assertEquals($context->traceId->__toString(), $data['trace_id']);
        $this->assertEquals($context->spanId->__toString(), $data['span_id']);
        $this->assertEquals($context->parentSpanId->__toString(), $data['parent_span_id']);
        $this->assertEquals($context->description, $data['description']);
        $this->assertEquals($context->status, $data['status']);
        $this->assertEquals($context->tags, $data['tags']);
        $this->assertEquals($context->data, $data['data']);
        $this->assertEquals($context->startTimestamp, $data['start_timestamp']);
        $this->assertEquals($context->endTimestamp, $data['timestamp']);
    }

    public function testFinish(): void
    {
        $span = new Span();
        $span->finish();
        $this->assertIsFloat($span->jsonSerialize()['timestamp']);

        $time = microtime(true);
        $span = new Span();
        $span->finish($time);
        $this->assertEquals($span->jsonSerialize()['timestamp'], $time);
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
