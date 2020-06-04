<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Context\Context;
use Sentry\Context\TagsContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * @group time-sensitive
 */
final class TransactionTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = new TransactionContext();
        $context->traceId = TraceId::generate();
        $context->spanId = SpanId::generate();
        $context->parentSpanId = SpanId::generate();
        $context->description = 'description';
        $context->name = 'name';
        $context->op = 'op';
        $context->status = 'ok';
        $context->sampled = true;
        $tags = new TagsContext();
        $tags['a'] = 'b';
        $context->tags = $tags;
        $data = new Context();
        $data['c'] = 'd';
        $context->data = $data;
        $context->startTimestamp = microtime(true);
        $context->endTimestamp = microtime(true);
        $transaction = new Transaction($context);
        $data = $transaction->jsonSerialize();

        $this->assertEquals($context->op, $data['contexts']['trace']['op']);
        $this->assertEquals($context->name, $data['transaction']);
        $this->assertEquals($context->traceId->__toString(), $data['contexts']['trace']['trace_id']);
        $this->assertEquals($context->spanId->__toString(), $data['contexts']['trace']['span_id']);
        $this->assertEquals($context->parentSpanId->__toString(), $data['contexts']['trace']['parent_span_id']);
        $this->assertEquals($context->description, $data['contexts']['trace']['description']);
        $this->assertEquals($context->status, $data['contexts']['trace']['status']);
        $this->assertEquals($context->tags->toArray(), $data['tags']);
        $this->assertEquals($context->data->toArray(), $data['contexts']['trace']['data']);
        $this->assertEquals($context->startTimestamp, $data['start_timestamp']);
        $this->assertEquals($context->endTimestamp, $data['timestamp']);
    }
}
