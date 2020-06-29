<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Context\Context;
use Sentry\Context\TagsContext;
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
        $data = $transaction->jsonSerialize();

        $this->assertEquals($context->op, $data['contexts']['trace']['op']);
        $this->assertEquals($context->name, $data['transaction']);
        $this->assertEquals($context->traceId->__toString(), $data['contexts']['trace']['trace_id']);
        $this->assertEquals($context->spanId->__toString(), $data['contexts']['trace']['span_id']);
        $this->assertEquals($context->parentSpanId->__toString(), $data['contexts']['trace']['parent_span_id']);
        $this->assertEquals($context->description, $data['contexts']['trace']['description']);
        $this->assertEquals($context->status, $data['contexts']['trace']['status']);
        $this->assertEquals($context->tags, $data['tags']);
        $this->assertEquals($context->data, $data['contexts']['trace']['data']);
        $this->assertEquals($context->startTimestamp, $data['start_timestamp']);
        $this->assertEquals($context->endTimestamp, $data['timestamp']);
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
        $data = $transaction->jsonSerialize();
        $this->assertCount(2, $data['spans']);
    }

    public function testStartAndSendTransaction(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['traces_sample_rate' => 1]));

        $hub = new Hub($client);
        $transaction = $hub->startTransaction(new TransactionContext());
        $span1 = $transaction->startChild(new SpanContext());
        $span2 = $transaction->startChild(new SpanContext());
        $span1->finish();
        $span2->finish();
        $endTimestamp = microtime(true);
        $data = $transaction->jsonSerialize();

        // We fake the endtime here
        $data['timestamp'] = $endTimestamp;
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function ($event) use ($data): bool {
                $this->assertEqualWithIgnore($data, $event->toArray(), ['event_id']);

                return true;
            }));

        $transaction->finish($endTimestamp);

        $this->assertCount(2, $data['spans']);
    }

    private function assertEqualWithIgnore($expected, $actual, $ignoreKeys = [], $currentKey = null): void
    {
        if (\is_object($expected)) {
            foreach ($expected as $key => $value) {
                $this->assertEqualWithIgnore($expected->$key, $actual->$key, $ignoreKeys, $key);
            }
        } elseif (\is_array($expected)) {
            foreach ($expected as $key => $value) {
                $this->assertEqualWithIgnore($expected[$key], $actual[$key], $ignoreKeys, $key);
            }
        } elseif (null !== $currentKey && !\in_array($currentKey, $ignoreKeys)) {
            $this->assertEquals($expected, $actual);
        }
    }
}
