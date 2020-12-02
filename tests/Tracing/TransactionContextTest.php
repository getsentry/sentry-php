<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\TransactionContext;

final class TransactionContextTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $transactionContext = new TransactionContext();

        $this->assertSame('<unlabeled transaction>', $transactionContext->getName());
        $this->assertNull($transactionContext->getParentSampled());

        $transactionContext->setName('foo');
        $transactionContext->setParentSampled(true);

        $this->assertSame('foo', $transactionContext->getName());
        $this->assertTrue($transactionContext->getParentSampled());
    }

    /**
     * @dataProvider fromSentryTraceDataProvider
     */
    public function testFromTraceparent(string $header, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedParentSampled): void
    {
        $spanContext = TransactionContext::fromSentryTrace($header);

        if (null !== $expectedSpanId) {
            $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        }

        if (null !== $expectedTraceId) {
            $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        }

        $this->assertSame($expectedParentSampled, $spanContext->getParentSampled());
    }

    public function fromSentryTraceDataProvider(): iterable
    {
        yield [
            '0',
            null,
            null,
            false,
        ];

        yield [
            '1',
            null,
            null,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            false,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            null,
        ];
    }
}
