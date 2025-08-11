<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Options;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

final class PropagationContextTest extends TestCase
{
    public function testFromDefaults()
    {
        $propagationContext = PropagationContext::fromDefaults();

        $this->assertInstanceOf(TraceId::class, $propagationContext->getTraceId());
        $this->assertInstanceOf(SpanId::class, $propagationContext->getSpanId());
        $this->assertNull($propagationContext->getParentSpanId());
        $this->assertNull($propagationContext->getDynamicSamplingContext());
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromHeaders(string $sentryTraceHeader, string $baggageHeader, ?TraceId $expectedTraceId, ?SpanId $expectedParentSpanId, ?bool $expectedDynamicSamplingContextFrozen)
    {
        $propagationContext = PropagationContext::fromHeaders($sentryTraceHeader, $baggageHeader);

        $this->assertInstanceOf(TraceId::class, $propagationContext->getTraceId());
        if ($expectedTraceId !== null) {
            $this->assertSame((string) $expectedTraceId, (string) $propagationContext->getTraceId());
        }

        $this->assertInstanceOf(SpanId::class, $propagationContext->getParentSpanId());
        if ($expectedParentSpanId !== null) {
            $this->assertSame((string) $expectedParentSpanId, (string) $propagationContext->getParentSpanId());
        }

        $this->assertInstanceOf(SpanId::class, $propagationContext->getSpanId());
        $this->assertInstanceOf(DynamicSamplingContext::class, $propagationContext->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $propagationContext->getDynamicSamplingContext()->isFrozen());
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromEnvironment(string $sentryTrace, string $baggage, ?TraceId $expectedTraceId, ?SpanId $expectedParentSpanId, ?bool $expectedDynamicSamplingContextFrozen)
    {
        $propagationContext = PropagationContext::fromEnvironment($sentryTrace, $baggage);

        $this->assertInstanceOf(TraceId::class, $propagationContext->getTraceId());
        if ($expectedTraceId !== null) {
            $this->assertSame((string) $expectedTraceId, (string) $propagationContext->getTraceId());
        }

        $this->assertInstanceOf(SpanId::class, $propagationContext->getParentSpanId());
        if ($expectedParentSpanId !== null) {
            $this->assertSame((string) $expectedParentSpanId, (string) $propagationContext->getParentSpanId());
        }

        $this->assertInstanceOf(SpanId::class, $propagationContext->getSpanId());
        $this->assertInstanceOf(DynamicSamplingContext::class, $propagationContext->getDynamicSamplingContext());
        $this->assertSame($expectedDynamicSamplingContextFrozen, $propagationContext->getDynamicSamplingContext()->isFrozen());
    }

    public static function tracingDataProvider(): iterable
    {
        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            '',
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            new SpanId('566e3688a61d4bc8'),
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-public_key=public,sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1',
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            new SpanId('566e3688a61d4bc8'),
            true,
        ];

        yield [
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'foo=bar',
            new TraceId('566e3688a61d4bc888951642d6f14a19'),
            new SpanId('566e3688a61d4bc8'),
            true,
        ];
    }

    public function testToTraceparent()
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $propagationContext->toTraceparent());
    }

    public function testToBaggage()
    {
        $dynamicSamplingContext = DynamicSamplingContext::fromHeader('sentry-trace_id=566e3688a61d4bc888951642d6f14a19');
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setDynamicSamplingContext($dynamicSamplingContext);

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19', $propagationContext->toBaggage());
    }

    public function testGetTraceContext()
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $this->assertSame([
            'trace_id' => (string) $propagationContext->getTraceId(),
            'span_id' => (string) $propagationContext->getSpanId(),
        ], $propagationContext->getTraceContext());

        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));
        $propagationContext->setParentSpanId(new SpanId('b01b9f6349558cd1'));

        $this->assertSame([
            'trace_id' => (string) $propagationContext->getTraceId(),
            'span_id' => (string) $propagationContext->getSpanId(),
            'parent_span_id' => (string) $propagationContext->getParentSpanId(),
        ], $propagationContext->getTraceContext());
    }

    public function testSampleRandRangeWhenParentNotSampledAndSampleRateProvided(): void
    {
        $propagationContext = PropagationContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
            'sentry-sample_rate=0.4'
        );

        $sampleRand = $propagationContext->getSampleRand();

        $this->assertNotNull($sampleRand);
        // Should be within [rate, 1) and rounded to 6 decimals
        $this->assertGreaterThanOrEqual(0.4, $sampleRand);
        $this->assertLessThanOrEqual(0.999999, $sampleRand);
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $getterMethod, string $setterMethod, $expectedData): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->$setterMethod($expectedData);

        $this->assertEquals($expectedData, $propagationContext->$getterMethod());
    }

    public static function gettersAndSettersDataProvider(): array
    {
        $scope = new Scope();
        $options = new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'release' => '1.0.0',
            'environment' => 'test',
        ]);

        $dynamicSamplingContext = DynamicSamplingContext::fromOptions($options, $scope);

        return [
            ['getTraceId', 'setTraceId', new TraceId('566e3688a61d4bc888951642d6f14a19')],
            ['getParentSpanId', 'setParentSpanId', new SpanId('566e3688a61d4bc8')],
            ['getSpanId', 'setSpanId', new SpanId('8c2df92a922b4efe')],
            ['getDynamicSamplingContext', 'setDynamicSamplingContext', $dynamicSamplingContext],
        ];
    }

    /**
     * Tests that strictTraceContinuation properly validates org IDs from incoming traces
     */
    public function testStrictTraceContinuationWithMatchingOrgId(): void
    {
        $client = $this->createMock(\Sentry\ClientInterface::class);
        $options = $this->createMock(\Sentry\Options::class);
        $dsn = $this->createMock(\Sentry\Dsn::class);
        
        $options->expects($this->once())
            ->method('isStrictTraceContinuationEnabled')
            ->willReturn(true);
        
        $options->expects($this->exactly(2))
            ->method('getDsn')
            ->willReturn($dsn);
        
        $dsn->expects($this->once())
            ->method('getOrgId')
            ->willReturn(123);
        
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);
        
        $sentryTrace = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';
        $baggage = 'sentry-org_id=123,sentry-trace_id=566e3688a61d4bc888951642d6f14a19';
        
        $context = PropagationContext::fromHeaders($sentryTrace, $baggage, $client);
        
        // Should continue the trace since org IDs match
        $this->assertEquals(new TraceId('566e3688a61d4bc888951642d6f14a19'), $context->getTraceId());
        $this->assertEquals(new SpanId('566e3688a61d4bc8'), $context->getParentSpanId());
        $this->assertTrue($context->getParentSampled());
    }

    /**
     * Tests that strictTraceContinuation creates a new trace when org IDs don't match
     */
    public function testStrictTraceContinuationWithMismatchedOrgId(): void
    {
        $client = $this->createMock(\Sentry\ClientInterface::class);
        $options = $this->createMock(\Sentry\Options::class);
        $dsn = $this->createMock(\Sentry\Dsn::class);
        
        $options->expects($this->once())
            ->method('isStrictTraceContinuationEnabled')
            ->willReturn(true);
        
        $options->expects($this->exactly(2))
            ->method('getDsn')
            ->willReturn($dsn);
        
        $dsn->expects($this->once())
            ->method('getOrgId')
            ->willReturn(123);
        
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);
        
        $sentryTrace = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';
        $baggage = 'sentry-org_id=456,sentry-trace_id=566e3688a61d4bc888951642d6f14a19';
        
        $context = PropagationContext::fromHeaders($sentryTrace, $baggage, $client);
        
        // Should create a new trace since org IDs don't match
        $this->assertNotEquals(new TraceId('566e3688a61d4bc888951642d6f14a19'), $context->getTraceId());
        $this->assertNull($context->getParentSpanId());
        $this->assertNull($context->getParentSampled());
        $this->assertNotNull($context->getSampleRand());
    }

    /**
     * Tests that strictTraceContinuation is disabled by default
     */
    public function testStrictTraceContinuationDisabledByDefault(): void
    {
        $client = $this->createMock(\Sentry\ClientInterface::class);
        $options = $this->createMock(\Sentry\Options::class);
        
        $options->expects($this->once())
            ->method('isStrictTraceContinuationEnabled')
            ->willReturn(false);
        
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);
        
        $sentryTrace = '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1';
        $baggage = 'sentry-org_id=456,sentry-trace_id=566e3688a61d4bc888951642d6f14a19';
        
        $context = PropagationContext::fromHeaders($sentryTrace, $baggage, $client);
        
        // Should continue the trace even with mismatched org IDs since strictTraceContinuation is disabled
        $this->assertEquals(new TraceId('566e3688a61d4bc888951642d6f14a19'), $context->getTraceId());
        $this->assertEquals(new SpanId('566e3688a61d4bc8'), $context->getParentSpanId());
        $this->assertTrue($context->getParentSampled());
    }
}
