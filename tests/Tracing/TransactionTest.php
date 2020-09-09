<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\SpanRecorder;
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
        $tags = [];
        $tags['a'] = 'b';
        $context->tags = $tags;
        $data = [];
        $data['c'] = 'd';
        $context->data = $data;
        $context->startTimestamp = microtime(true);
        $context->endTimestamp = microtime(true);
        $transaction = new Transaction($context);
        $data = $transaction->toEvent();
        $traceContext = $data->getContexts()['trace'];

        $this->assertEquals($context->op, $traceContext['op']);
        $this->assertEquals($context->name, $data->getTransaction());
        $this->assertEquals($context->traceId->__toString(), $traceContext['trace_id']);
        $this->assertEquals($context->spanId->__toString(), $traceContext['span_id']);
        $this->assertEquals($context->parentSpanId->__toString(), $traceContext['parent_span_id']);
        $this->assertEquals($context->description, $traceContext['description']);
        $this->assertEquals($context->status, $traceContext['status']);
        $this->assertEquals($context->tags, $data->getTags());
        $this->assertEquals($context->data, $traceContext['data']);
        $this->assertEquals($context->startTimestamp, $data->getStartTimestamp());
        $this->assertEquals($context->endTimestamp, $data->getTimestamp());
    }

    public function testShouldContainFinishSpans(): void
    {
        $transaction = new Transaction(new TransactionContext());
        $transaction->spanRecorder = new SpanRecorder();
        $span1 = $transaction->startChild(new SpanContext());
        $span2 = $transaction->startChild(new SpanContext());
        $span3 = $transaction->startChild(new SpanContext());
        $span1->finish();
        $span2->finish();
        // $span3 is not finished and therefore not included
        $transaction->finish();
        $data = $transaction->toEvent();
        $this->assertCount(2, $data->getSpans());
    }

    public function testStartAndSendTransaction(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
            ->method('getOptions')
            ->willReturn(new Options(['traces_sample_rate' => 1]));

        $hub = new Hub($client);
        $transaction = $hub->startTransaction(new TransactionContext());
        $span1 = $transaction->startChild(new SpanContext());
        $span2 = $transaction->startChild(new SpanContext());
        $span1->finish();
        $span2->finish();

        $data = $transaction->toEvent();

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $eventArg) use ($data): bool {
                $this->assertSame(EventType::transaction(), $eventArg->getType());
                $this->assertSame($data->getStartTimestamp(), $eventArg->getStartTimestamp());
                $this->assertSame(microtime(true), $eventArg->getTimestamp());
                $this->assertCount(2, $data->getSpans());

                return true;
            }));

        $transaction->finish(microtime(true));
    }
}
