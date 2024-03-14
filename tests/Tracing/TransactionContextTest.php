<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Tracing\TransactionSource;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class TransactionContextTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testGettersAndSetters(): void
    {
        $transactionContext = new TransactionContext();
        $transactionMetadata = new TransactionMetadata();
        $transactionSource = TransactionSource::custom();

        $this->assertSame('<unlabeled transaction>', $transactionContext->getName());
        $this->assertNull($transactionContext->getParentSampled());

        $transactionContext->setName('foo');
        $transactionContext->setParentSampled(true);
        $transactionContext->setMetadata($transactionMetadata);
        $transactionContext->setSource($transactionSource);

        $this->assertSame('foo', $transactionContext->getName());
        $this->assertTrue($transactionContext->getParentSampled());
        $this->assertSame($transactionMetadata, $transactionContext->getMetadata());
        $this->assertSame($transactionSource, $transactionContext->getMetadata()->getSource());
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromHeaders(string $sentryTraceHeader, string $baggageHeader, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedParentSampled, ?string $expectedDynamicSamplingContextClass, ?bool $expectedDynamicSamplingContextFrozen)
    {
        $spanContext = TransactionContext::fromHeaders($sentryTraceHeader, $baggageHeader);

        $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        $this->assertSame($expectedParentSampled, $spanContext->getParentSampled());
        $this->assertInstanceOf($expectedDynamicSamplingContextClass, $spanContext->getMetadata()->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $spanContext->getMetadata()->getDynamicSamplingContext()->isFrozen());
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromEnvironment(string $sentryTrace, string $baggage, ?SpanId $expectedSpanId, ?TraceId $expectedTraceId, ?bool $expectedParentSampled, ?string $expectedDynamicSamplingContextClass, ?bool $expectedDynamicSamplingContextFrozen)
    {
        $spanContext = TransactionContext::fromEnvironment($sentryTrace, $baggage);

        $this->assertEquals($expectedSpanId, $spanContext->getParentSpanId());
        $this->assertEquals($expectedTraceId, $spanContext->getTraceId());
        $this->assertSame($expectedParentSampled, $spanContext->getParentSampled());
        $this->assertInstanceOf($expectedDynamicSamplingContextClass, $spanContext->getMetadata()->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $spanContext->getMetadata()->getDynamicSamplingContext()->isFrozen());
    }

    public static function tracingDataProvider(): iterable
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
            '00-566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-00',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            false,
            DynamicSamplingContext::class,
            true,
        ];

        yield [
            '00-566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-01',
            '',
            new SpanId('566e3688a61d4bc8'),
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            true,
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
