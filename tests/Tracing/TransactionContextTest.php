<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionMetadata;

final class TransactionContextTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $transactionContext = new TransactionContext();
        $transactionMetadata = new TransactionMetadata();

        $this->assertSame('<unlabeled transaction>', $transactionContext->getName());
        $this->assertNull($transactionContext->getParentSampled());

        $transactionContext->setName('foo');
        $transactionContext->setParentSampled(true);
        $transactionContext->setMetadata($transactionMetadata);

        $this->assertSame('foo', $transactionContext->getName());
        $this->assertTrue($transactionContext->getParentSampled());
        $this->assertSame($transactionMetadata, $transactionContext->getMetadata());
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

    /**
     * @dataProvider fromHeadersDataProvider
     */
    public function testFromHeaders(string $sentryTraceHeader, string $baggageHeader, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedParentSampled, ?string $expectedDynamicSamplingContextClass, ?bool $expectedDynamicSamplingContextFrozen)
    {
        $spanContext = TransactionContext::fromHeaders($sentryTraceHeader, $baggageHeader);

        if (null !== $expectedSpanId) {
            $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        }

        if (null !== $expectedTraceId) {
            $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        }

        $this->assertSame($expectedParentSampled, $spanContext->getParentSampled());

        $this->assertInstanceOf($expectedDynamicSamplingContextClass, $spanContext->getMetadata()->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $spanContext->getMetadata()->getDynamicSamplingContext()->isFrozen());
    }

    public function fromHeadersDataProvider(): iterable
    {
        yield [
            '0',
            '',
            null,
            null,
            false,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '1',
            '',
            null,
            null,
            true,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            false,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            null,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-public_key=public,sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'foo=bar',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
            DynamicSamplingContext::class,
            true,
        ];
    }
}
