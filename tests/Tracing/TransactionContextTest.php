<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Options;
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
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-environment=production,sentry-public_key=49d0f7c7e546418e8b684ff47a6c4fae,sentry-trace_id=566e3688a61d4bc888951642d6f14a19',
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

    /**
     * Tests that org ID validation allows trace continuation when org IDs match.
     */
    public function testStrictTraceContinuationWithMatchingOrgId(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'https://public_key@sentry.io/project_id',
                'org_id' => 123,
            ]));

        $sentryTrace = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';
        $baggage = 'sentry-org_id=123,sentry-trace_id=566e3688a61d4bc888951642d6f14a19';

        $context = TransactionContext::fromHeaders($sentryTrace, $baggage, $client);

        // Should continue the trace since org IDs match
        $this->assertEquals(new TraceId('566e3688a61d4bc888951642d6f14a19'), $context->getTraceId());
        $this->assertEquals(new SpanId('566e3688a61d4bc8'), $context->getParentSpanId());
        $this->assertTrue($context->getParentSampled());
    }

    /**
     * Tests that org ID validation creates a new trace when org IDs don't match.
     */
    public function testStrictTraceContinuationWithMismatchedOrgId(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'strict_trace_continuation' => true,
                'dsn' => 'https://public_key@sentry.io/project_id',
                'org_id' => 123,
            ]));

        $sentryTrace = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';
        $baggage = 'sentry-org_id=456,sentry-trace_id=566e3688a61d4bc888951642d6f14a19';

        $context = TransactionContext::fromHeaders($sentryTrace, $baggage, $client);

        // Should create a new trace since org IDs don't match
        $this->assertNotEquals(new TraceId('566e3688a61d4bc888951642d6f14a19'), $context->getTraceId());
        $this->assertNull($context->getParentSpanId());
        $this->assertNull($context->getParentSampled());
        $this->assertNotNull($context->getMetadata()->getSampleRand());
    }

    /**
     * Tests that org ID validation happens regardless of strictTraceContinuation setting.
     */
    public function testOrgIdValidationAlwaysHappens(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'https://public_key@sentry.io/project_id',
                'org_id' => 123,
                // strictTraceContinuation is not set (defaults to false)
            ]));

        $sentryTrace = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';
        $baggage = 'sentry-org_id=456,sentry-trace_id=566e3688a61d4bc888951642d6f14a19';

        $context = TransactionContext::fromHeaders($sentryTrace, $baggage, $client);

        // Should create a new trace when org IDs don't match, even without strictTraceContinuation enabled
        $this->assertNotEquals(new TraceId('566e3688a61d4bc888951642d6f14a19'), $context->getTraceId());
        $this->assertNull($context->getParentSpanId());
        $this->assertNull($context->getParentSampled());
        $this->assertNotNull($context->getMetadata()->getSampleRand());
    }

    public function testSampleRandRangeWhenParentNotSampledAndSampleRateProvided(): void
    {
        $context = TransactionContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            'sentry-sample_rate=0.4'
        );

        $sampleRand = $context->getMetadata()->getSampleRand();

        $this->assertNotNull($sampleRand);
        // Should be within [rate, 1) and rounded to 6 decimals
        $this->assertGreaterThanOrEqual(0.4, $sampleRand);
        $this->assertLessThanOrEqual(0.999999, $sampleRand);
    }
}
