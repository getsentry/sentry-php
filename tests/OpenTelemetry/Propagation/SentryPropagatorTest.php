<?php

declare(strict_types=1);

namespace Sentry\Tests\OpenTelemetry\Propagation;

use OpenTelemetry\API\Instrumentation\ContextKeys;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\SDK\Propagation\PropagatorFactory;
use OpenTelemetry\SDK\Trace\Span;
use PHPUnit\Framework\TestCase;
use Sentry\OpenTelemetry\Propagation\SentryPropagator;

class SentryPropagatorTest extends TestCase
{
    private const TRACE_ID_BASE16 = 'ff000000000000000000000000000041';
    private const SPAN_ID_BASE16 = 'ff00000000000041';
    private const SENTRY_TRACE_HEADER_SAMPLED = self::TRACE_ID_BASE16 . '-' . self::SPAN_ID_BASE16 . '-1';
    private const SENTRY_TRACE_HEADER_NOT_SAMPLED = self::TRACE_ID_BASE16 . '-' . self::SPAN_ID_BASE16 . '-0';
    private const SENTRY_TRACE_HEADER_OPTIONAL_SAMPLING = self::TRACE_ID_BASE16 . '-' . self::SPAN_ID_BASE16;

    private const TRACE_PARENT_SAMPLED = '00-' . self::TRACE_ID_BASE16 . '-' . self::SPAN_ID_BASE16 . '-01';

    private const TRACE_ID_OTHER_BASE16 = 'ff000000000000000000000000000042';
    private const SPAN_ID_OTHER_BASE16 = 'ff00000000000042';
    private const TRACE_PARENT_OTHER_SAMPLED = '00-' . self::TRACE_ID_OTHER_BASE16 . '-' . self::SPAN_ID_OTHER_BASE16 . '-01';

    public function testFields(): void
    {
        $propagator = new SentryPropagator();

        $this->assertSame(['sentry-trace'], $propagator->fields());
    }

    public function testInjectEmpty(): void
    {
        $propagator = new SentryPropagator();
        $carrier = [];
        $propagator->inject($carrier);

        $this->assertEmpty($carrier);
    }

    public function testInjectInvalidContext(): void
    {
        $propagator = new SentryPropagator();
        $carrier = [];
        $propagator->inject($carrier, null, $this->withSpanContext(SpanContext::getInvalid(), Context::getCurrent()));

        $this->assertEmpty($carrier);
    }

    /**
     * @dataProvider injectDataProvider
     */
    public function testInject(string $traceId, string $spanId, bool $sampled, string $expected): void
    {
        $propagator = new SentryPropagator();
        $carrier = [];
        $propagator->inject(
            $carrier,
            null,
            $this->withSpanContext(
                SpanContext::create($traceId, $spanId, $sampled ? TraceFlags::SAMPLED : TraceFlags::DEFAULT),
                Context::getCurrent()
            )
        );

        $this->assertSame(
            ['sentry-trace' => $expected],
            $carrier
        );
    }

    public function injectDataProvider(): array
    {
        return [
            [self::TRACE_ID_BASE16, self::SPAN_ID_BASE16, true, self::SENTRY_TRACE_HEADER_SAMPLED],
            [self::TRACE_ID_BASE16, self::SPAN_ID_BASE16, false, self::SENTRY_TRACE_HEADER_NOT_SAMPLED],
        ];
    }

    public function testExtractEmpty(): void
    {
        $propagator = new SentryPropagator();

        $this->assertEquals(
            Context::getCurrent(),
            $propagator->extract([])
        );
    }

    public function testExtractEmptyHeader(): void
    {
        $propagator = new SentryPropagator();

        $this->assertEquals(
            Context::getCurrent(),
            $propagator->extract([
                SentryPropagator::SENTRY_TRACE => '',
            ])
        );
    }

    public function testExtractInvalidHeader(): void
    {
        $propagator = new SentryPropagator();

        $this->assertEquals(
            Context::getCurrent(),
            $propagator->extract([
                SentryPropagator::SENTRY_TRACE => 'invalid',
            ])
        );
    }

    /**
     * @dataProvider extractDataProvider
     */
    public function testExtract(string $sentryTraceHeader, int $expectedTraceFlags): void
    {
        $propagator = new SentryPropagator();
        $carrier = [
            SentryPropagator::SENTRY_TRACE => $sentryTraceHeader,
        ];

        $this->assertEquals(
            SpanContext::createFromRemoteParent(self::TRACE_ID_BASE16, self::SPAN_ID_BASE16, $expectedTraceFlags),
            Span::fromContext($propagator->extract($carrier))->getContext()
        );
    }

    public function extractDataProvider(): array
    {
        return [
            [self::SENTRY_TRACE_HEADER_SAMPLED, TraceFlags::SAMPLED],
            [self::SENTRY_TRACE_HEADER_NOT_SAMPLED, TraceFlags::DEFAULT],
            [self::SENTRY_TRACE_HEADER_OPTIONAL_SAMPLING, TraceFlags::DEFAULT],
        ];
    }

    // sam traceparent + tylko sentry propagator: current context
    // traceparent oraz sentry-trace + tylko sentry propagator: trace z sentry-trace
    // traceparent oraz sentry-trace + trace context oraz sentry propagator: trace z traceparent

    public function testExtractInvalidOnlyTraceparent(): void
    {
        $propagator = new SentryPropagator();

        $this->assertEquals(
            Context::getCurrent(),
            $propagator->extract([
                TraceContextPropagator::TRACEPARENT => self::TRACE_PARENT_SAMPLED,
            ])
        );
    }

    public function testExtractWithTraceparentIgnored(): void
    {
        $propagator = new SentryPropagator();
        $carrier = [
            SentryPropagator::SENTRY_TRACE => self::SENTRY_TRACE_HEADER_SAMPLED,
            TraceContextPropagator::TRACEPARENT => self::TRACE_PARENT_OTHER_SAMPLED,
        ];

        $this->assertEquals(
            SpanContext::createFromRemoteParent(self::TRACE_ID_BASE16, self::SPAN_ID_BASE16, TraceFlags::SAMPLED),
            Span::fromContext($propagator->extract($carrier))->getContext()
        );
    }

    public function testExtractWithSentryTraceIgnored(): void
    {
        $propagator = new MultiTextMapPropagator([
            new SentryPropagator(),
            new TraceContextPropagator(),
        ]);

        $context = Context::getCurrent();
        $context = $context->with(ContextKeys::propagator(), $propagator);

        $carrier = [
            SentryPropagator::SENTRY_TRACE => self::SENTRY_TRACE_HEADER_SAMPLED,
            TraceContextPropagator::TRACEPARENT => self::TRACE_PARENT_OTHER_SAMPLED,
        ];

        $this->assertEquals(
            SpanContext::createFromRemoteParent(self::TRACE_ID_OTHER_BASE16, self::SPAN_ID_OTHER_BASE16, TraceFlags::SAMPLED),
            Span::fromContext($propagator->extract($carrier, null, $context))->getContext()
        );
    }

    public function testExtractAndInject(): void
    {
        $propagator = new SentryPropagator();
        $extractCarrier = [
            SentryPropagator::SENTRY_TRACE => self::SENTRY_TRACE_HEADER_SAMPLED,
        ];

        $context = $propagator->extract($extractCarrier);
        $injectCarrier = [];
        $propagator->inject($injectCarrier, null, $context);

        $this->assertSame($injectCarrier, $extractCarrier);
    }

    public function testPropagatorFactory(): void
    {
        $_SERVER['OTEL_PROPAGATORS'] = 'sentry';
        $propagator = (new PropagatorFactory())->create();

        $this->assertInstanceOf(SentryPropagator::class, $propagator);
    }

    protected function tearDown(): void
    {
        if ($this->getName() === 'testPropagatorFactory') {
            unset($_SERVER['OTEL_PROPAGATORS']);
        }

        parent::tearDown();
    }

    private function withSpanContext(SpanContextInterface $spanContext, ContextInterface $context): ContextInterface
    {
        return $context->withContextValue(Span::wrap($spanContext));
    }
}
