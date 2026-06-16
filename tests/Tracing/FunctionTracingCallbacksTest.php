<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Integration\FunctionTracingIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Tests\StubTransport;
use Sentry\Tracing\FunctionTracingCallbacks;
use Sentry\Tracing\FunctionTracingState;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

final class FunctionTracingCallbacksTest extends TestCase
{
    public function testHandleStartCreatesSpanAndSetsItActive(): void
    {
        $transaction = $this->createActiveTransaction(true, true);

        $callbackState = FunctionTracingCallbacks::handleStart([
            'name' => 'foo',
            'start_time' => 123.456,
            'metadata' => [],
        ]);

        $this->assertInstanceOf(FunctionTracingState::class, $callbackState);
        $this->assertSame($transaction, $callbackState->getParentSpan());
        $this->assertSame($transaction->getSpanId(), $callbackState->getCurrentSpan()->getParentSpanId());
        $this->assertSame($transaction->getTraceId(), $callbackState->getCurrentSpan()->getTraceId());
        $this->assertSame(123.456, $callbackState->getCurrentSpan()->getStartTimestamp());
        $this->assertSame($callbackState->getCurrentSpan(), SentrySdk::getCurrentHub()->getSpan());
    }

    public function testHandleEndFinishesSpanWithEndTimeAndRestoresParent(): void
    {
        $transaction = $this->createActiveTransaction(true, true);

        $callbackState = FunctionTracingCallbacks::handleStart([
            'name' => 'foo',
            'start_time' => 123.456,
            'metadata' => [],
        ]);

        $this->assertInstanceOf(FunctionTracingState::class, $callbackState);

        FunctionTracingCallbacks::handleEnd([
            'name' => 'foo',
            'start_time' => 123.456,
            'end_time' => 234.567,
            'duration' => 111.111,
            'metadata' => [],
        ], $callbackState);

        $this->assertSame(234.567, $callbackState->getCurrentSpan()->getEndTimestamp());
        $this->assertSame($transaction, SentrySdk::getCurrentHub()->getSpan());
    }

    public function testNestedCallbacksRestoreParentSpansInOrder(): void
    {
        $transaction = $this->createActiveTransaction(true, true);

        $outerState = FunctionTracingCallbacks::handleStart([
            'name' => 'outer',
            'start_time' => 1.0,
            'metadata' => [],
        ]);

        $this->assertInstanceOf(FunctionTracingState::class, $outerState);

        $innerState = FunctionTracingCallbacks::handleStart([
            'name' => 'inner',
            'start_time' => 2.0,
            'metadata' => [],
        ]);

        $this->assertInstanceOf(FunctionTracingState::class, $innerState);
        $this->assertSame($outerState->getCurrentSpan()->getSpanId(), $innerState->getCurrentSpan()->getParentSpanId());
        $this->assertSame($innerState->getCurrentSpan(), SentrySdk::getCurrentHub()->getSpan());

        FunctionTracingCallbacks::handleEnd([
            'name' => 'inner',
            'start_time' => 2.0,
            'end_time' => 3.0,
            'duration' => 1.0,
            'metadata' => [],
        ], $innerState);

        $this->assertSame($outerState->getCurrentSpan(), SentrySdk::getCurrentHub()->getSpan());

        FunctionTracingCallbacks::handleEnd([
            'name' => 'outer',
            'start_time' => 1.0,
            'end_time' => 4.0,
            'duration' => 3.0,
            'metadata' => [],
        ], $outerState);

        $this->assertSame($transaction, SentrySdk::getCurrentHub()->getSpan());
    }

    /**
     * @dataProvider handleStartNoOpDataProvider
     */
    public function testHandleStartReturnsNullWhenSpanCannotBeCreated(bool $withIntegration, bool $withActiveSpan, bool $sampled): void
    {
        $this->createHub($withIntegration);

        if ($withActiveSpan) {
            $transaction = SentrySdk::getCurrentHub()->startTransaction(new TransactionContext());
            $transaction->setSampled($sampled);
            SentrySdk::getCurrentHub()->setSpan($transaction);
        }

        $this->assertNull(FunctionTracingCallbacks::handleStart([
            'name' => 'foo',
            'start_time' => 123.456,
            'metadata' => [],
        ]));
    }

    public static function handleStartNoOpDataProvider(): iterable
    {
        yield 'disabled integration' => [false, true, true];
        yield 'no active span' => [true, false, true];
        yield 'unsampled active span' => [true, true, false];
    }

    /**
     * @dataProvider malformedCallbackStateDataProvider
     *
     * @param mixed $callbackState
     */
    public function testHandleEndIgnoresMalformedCallbackState($callbackState): void
    {
        $transaction = $this->createActiveTransaction(true, true);

        FunctionTracingCallbacks::handleEnd([
            'name' => 'foo',
            'start_time' => 123.456,
            'end_time' => 234.567,
            'duration' => 111.111,
            'metadata' => [],
        ], $callbackState);

        $this->assertSame($transaction, SentrySdk::getCurrentHub()->getSpan());
    }

    public static function malformedCallbackStateDataProvider(): iterable
    {
        $span = new Span();

        yield 'null' => [null];
        yield 'missing parent' => [[$span]];
        yield 'invalid child' => [['foo', $span]];
        yield 'invalid parent' => [[$span, 'foo']];
    }

    public function testMetadataIsMappedToSpanContext(): void
    {
        $this->createActiveTransaction(true, true);

        $callbackState = FunctionTracingCallbacks::handleStart([
            'name' => 'foo',
            'start_time' => 123.456,
            'metadata' => [
                'description' => 'custom description',
                'op' => 'custom.op',
                'origin' => 'auto.custom',
                'custom' => 'value',
            ],
        ]);

        $this->assertInstanceOf(FunctionTracingState::class, $callbackState);
        $span = $callbackState->getCurrentSpan();

        $this->assertSame('custom description', $span->getDescription());
        $this->assertSame('custom.op', $span->getOp());
        $this->assertSame('auto.custom', $span->getOrigin());
        $this->assertSame(['custom' => 'value'], $span->getData());
    }

    public function testMetadataDefaultsAreUsed(): void
    {
        $this->createActiveTransaction(true, true);

        $callbackState = FunctionTracingCallbacks::handleStart([
            'name' => 'foo',
            'start_time' => 123.456,
            'metadata' => [
                'custom' => 'value',
            ],
        ]);

        $this->assertInstanceOf(FunctionTracingState::class, $callbackState);
        $span = $callbackState->getCurrentSpan();

        $this->assertSame('foo', $span->getDescription());
        $this->assertSame('function', $span->getOp());
        $this->assertSame('auto.php.tracer', $span->getOrigin());
        $this->assertSame(['custom' => 'value'], $span->getData());
    }

    private function createActiveTransaction(bool $withIntegration, bool $sampled): Transaction
    {
        $hub = $this->createHub($withIntegration);
        $transaction = $hub->startTransaction(new TransactionContext());
        $transaction->setSampled($sampled);
        if ($sampled && $transaction->getSpanRecorder() === null) {
            $transaction->initSpanRecorder();
        }

        $hub->setSpan($transaction);

        return $transaction;
    }

    private function createHub(bool $withIntegration): Hub
    {
        $integrations = $withIntegration ? [new FunctionTracingIntegration()] : [];
        $options = new Options([
            'default_integrations' => false,
            'integrations' => $integrations,
            'traces_sample_rate' => 1.0,
        ]);

        $hub = new Hub(new Client($options, StubTransport::getInstance()));
        SentrySdk::setCurrentHub($hub);

        return $hub;
    }
}
