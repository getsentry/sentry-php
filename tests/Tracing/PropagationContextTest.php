<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\IsolationScope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;

final class PropagationContextTest extends TestCase
{
    public function testFromDefaults(): void
    {
        $propagationContext = PropagationContext::fromDefaults();

        $this->assertInstanceOf(TraceId::class, $propagationContext->getTraceId());
        $this->assertInstanceOf(SpanId::class, $propagationContext->getSpanId());
        $this->assertNull($propagationContext->getParentSpanId());
        $this->assertNull($propagationContext->getDynamicSamplingContext());
    }

    /**
     * A worker pool forks its workers after the SDK was initialized in the master process. The workers must not
     * continue the same random sequence, or all of them make the same sampling decisions.
     *
     * @requires extension pcntl
     * @requires extension posix
     */
    public function testForkedProcessesDrawDifferentSampleRands(): void
    {
        // Draws a first value in the parent process, like `init()` does in the master process
        PropagationContext::fromDefaults();

        $sampleRands = [];

        for ($fork = 0; $fork < 2; ++$fork) {
            $resultFile = tempnam(sys_get_temp_dir(), 'sentry-sample-rand-fork');

            $pid = pcntl_fork();

            if ($pid === 0) {
                $result = [];

                for ($i = 0; $i < 5; ++$i) {
                    $result[] = PropagationContext::fromDefaults()->getSampleRand();
                }

                file_put_contents($resultFile, json_encode($result));

                // Exits without running the shutdown of the test runner in the forked process
                posix_kill(getmypid(), \SIGKILL);
            }

            pcntl_waitpid($pid, $status);

            $sampleRands[] = json_decode((string) file_get_contents($resultFile), true);
            unlink($resultFile);
        }

        $this->assertCount(5, $sampleRands[0]);
        $this->assertCount(5, $sampleRands[1]);
        $this->assertNotSame($sampleRands[0], $sampleRands[1]);
    }

    /**
     * @dataProvider tracingDataProvider
     */
    public function testFromHeaders(string $sentryTraceHeader, string $baggageHeader, ?TraceId $expectedTraceId, ?SpanId $expectedParentSpanId, ?bool $expectedDynamicSamplingContextFrozen): void
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
    public function testFromEnvironment(string $sentryTrace, string $baggage, ?TraceId $expectedTraceId, ?SpanId $expectedParentSpanId, ?bool $expectedDynamicSamplingContextFrozen): void
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

    public function testToTraceparent(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $propagationContext->toTraceparent());
    }

    public function testToBaggage(): void
    {
        $dynamicSamplingContext = DynamicSamplingContext::fromHeader('sentry-trace_id=566e3688a61d4bc888951642d6f14a19');
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setDynamicSamplingContext($dynamicSamplingContext);

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19', $propagationContext->toBaggage());
    }

    public function testToBaggageUsesCurrentClientAndIsolationScope(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSampleRand(0.25);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'release' => '1.0.0',
                'environment' => 'development',
            ]));

        SentrySdk::getGlobalScope()->setClient($client);
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope(new IsolationScope($propagationContext));

        $this->assertSame(
            'sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rand=0.25,sentry-release=1.0.0,sentry-environment=development',
            $propagationContext->toBaggage()
        );
    }

    public function testGetTraceContext(): void
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

    /**
     * @dataProvider invalidSampleRandDataProvider
     */
    public function testInvalidSampleRandIsIgnored(string $sampleRand): void
    {
        $propagationContext = PropagationContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-sample_rate=0.4,sentry-sample_rand=' . rawurlencode($sampleRand)
        );

        $generatedSampleRand = $propagationContext->getSampleRand();

        $this->assertNotNull($generatedSampleRand);
        $this->assertGreaterThanOrEqual(0.0, $generatedSampleRand);
        $this->assertLessThan(0.4, $generatedSampleRand);
    }

    public function testSampleRandIsIgnoredWithoutSentryTraceHeader(): void
    {
        $propagationContext = PropagationContext::fromHeaders('', 'sentry-sample_rand=-1.0');
        $sampleRand = $propagationContext->getSampleRand();

        $this->assertNotNull($sampleRand);
        $this->assertGreaterThanOrEqual(0.0, $sampleRand);
        $this->assertLessThanOrEqual(1.0, $sampleRand);
    }

    public static function invalidSampleRandDataProvider(): iterable
    {
        yield ['-1.0'];
        yield ['1'];
        yield ['2.0'];
        yield ['foo'];
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
        $scope = new IsolationScope();
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
}
