<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Context\Context;
use Sentry\Context\TagsContext;
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
        $context->description = "description";
        $context->op = "op";
        $context->status = "ok";
        $context->sampled = true;
        $tags = new TagsContext();
        $tags['a'] = 'b';
        $context->tags = $tags;
        $data = new Context();
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
        $this->assertEquals($context->tags->toArray(), $data['tags']);
        $this->assertEquals($context->data->toArray(), $data['data']);
        $this->assertEquals($context->startTimestamp, $data['start_timestamp']);
        $this->assertEquals($context->endTimestamp, $data['timestamp']);
    }

}
