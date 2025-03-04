<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Util\SentryUid;

use function Sentry\addBreadcrumb;
use function Sentry\captureCheckIn;
use function Sentry\captureEvent;
use function Sentry\captureException;
use function Sentry\captureLastError;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\continueTrace;
use function Sentry\getBaggage;
use function Sentry\getTraceparent;
use function Sentry\getW3CTraceparent;
use function Sentry\init;
use function Sentry\startTransaction;
use function Sentry\trace;
use function Sentry\withMonitor;
use function Sentry\withScope;

final class FunctionsTest extends TestCase
{
    public function testInit(): void
    {
        init(['default_integrations' => false]);

        $this->assertNotNull(SentrySdk::getCurrentHub()->getClient());
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, captureMessage(...$functionCallArgs));
    }

    public static function captureMessageDataProvider(): \Generator
    {
        yield [
            [
                'foo',
                Severity::debug(),
            ],
            [
                'foo',
                Severity::debug(),
                null,
            ],
        ];

        yield [
            [
                'foo',
                Severity::debug(),
                new EventHint(),
            ],
            [
                'foo',
                Severity::debug(),
                new EventHint(),
            ],
        ];
    }

    /**
     * @dataProvider captureExceptionDataProvider
     */
    public function testCaptureException(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureException')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, captureException(...$functionCallArgs));
    }

    public static function captureExceptionDataProvider(): \Generator
    {
        yield [
            [
                new \Exception('foo'),
            ],
            [
                new \Exception('foo'),
                null,
            ],
        ];

        yield [
            [
                new \Exception('foo'),
                new EventHint(),
            ],
            [
                new \Exception('foo'),
                new EventHint(),
            ],
        ];
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent();
        $hint = new EventHint();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint)
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), captureEvent($event, $hint));
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureLastError')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        @trigger_error('foo', \E_USER_NOTICE);

        $this->assertSame($eventId, captureLastError(...$functionCallArgs));
    }

    public static function captureLastErrorDataProvider(): \Generator
    {
        yield [
            [],
            [null],
        ];

        yield [
            [new EventHint()],
            [new EventHint()],
        ];
    }

    public function testCaptureCheckIn(): void
    {
        $checkInId = SentryUid::generate();
        $monitorConfig = new MonitorConfig(
            MonitorSchedule::crontab('*/5 * * * *'),
            5,
            30,
            'UTC'
        );

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureCheckIn')
            ->with('test-crontab', CheckInStatus::ok(), 10, $monitorConfig, $checkInId)
            ->willReturn($checkInId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($checkInId, captureCheckIn(
            'test-crontab',
            CheckInStatus::ok(),
            10,
            $monitorConfig,
            $checkInId
        ));
    }

    public function testWithMonitor(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))
            ->method('captureCheckIn')
            ->with(
                $this->callback(function (string $slug): bool {
                    return $slug === 'test-crontab';
                }),
                $this->callback(function (CheckInStatus $checkInStatus): bool {
                    // just check for type CheckInStatus
                    return true;
                }),
                $this->anything(),
                $this->callback(function (MonitorConfig $monitorConfig): bool {
                    return $monitorConfig->getSchedule()->getValue() === '*/5 * * * *'
                        && $monitorConfig->getSchedule()->getType() === MonitorSchedule::TYPE_CRONTAB
                        && $monitorConfig->getCheckinMargin() === 5
                        && $monitorConfig->getMaxRuntime() === 30
                        && $monitorConfig->getTimezone() === 'UTC';
                })
            );

        SentrySdk::setCurrentHub($hub);

        withMonitor('test-crontab', function () {
            // Do something...
        }, new MonitorConfig(
            new MonitorSchedule(MonitorSchedule::TYPE_CRONTAB, '*/5 * * * *'),
            5,
            30,
            'UTC'
        ));
    }

    public function testWithMonitorCallableThrows(): void
    {
        $this->expectException(\Exception::class);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->exactly(2))
            ->method('captureCheckIn')
            ->with(
                $this->callback(function (string $slug): bool {
                    return $slug === 'test-crontab';
                }),
                $this->callback(function (CheckInStatus $checkInStatus): bool {
                    // just check for type CheckInStatus
                    return true;
                }),
                $this->anything(),
                $this->callback(function (MonitorConfig $monitorConfig): bool {
                    return $monitorConfig->getSchedule()->getValue() === '*/5 * * * *'
                        && $monitorConfig->getSchedule()->getType() === MonitorSchedule::TYPE_CRONTAB
                        && $monitorConfig->getCheckinMargin() === 5
                        && $monitorConfig->getMaxRuntime() === 30
                        && $monitorConfig->getTimezone() === 'UTC';
                })
            );

        SentrySdk::setCurrentHub($hub);

        withMonitor('test-crontab', function () {
            throw new \Exception();
        }, new MonitorConfig(
            new MonitorSchedule(MonitorSchedule::TYPE_CRONTAB, '*/5 * * * *'),
            5,
            30,
            'UTC'
        ));
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['default_integrations' => false]));

        SentrySdk::getCurrentHub()->bindClient($client);

        addBreadcrumb($breadcrumb);
        configureScope(function (Scope $scope) use ($breadcrumb): void {
            $event = $scope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        });
    }

    public function testWithScope(): void
    {
        $returnValue = withScope(static function (): string {
            return 'foobarbaz';
        });

        $this->assertSame('foobarbaz', $returnValue);
    }

    public function testConfigureScope(): void
    {
        $callbackInvoked = false;

        configureScope(static function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testStartTransaction(): void
    {
        $transactionContext = new TransactionContext('foo');
        $transaction = new Transaction($transactionContext);
        $customSamplingContext = ['foo' => 'bar'];

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('startTransaction')
            ->with($transactionContext, $customSamplingContext)
            ->willReturn($transaction);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($transaction, startTransaction($transactionContext, $customSamplingContext));
    }

    public function testTraceReturnsClosureResult(): void
    {
        $returnValue = 'foo';

        $result = trace(function () use ($returnValue) {
            return $returnValue;
        }, new SpanContext());

        $this->assertSame($returnValue, $result);
    }

    public function testTraceCorrectlyReplacesAndRestoresCurrentSpan(): void
    {
        $hub = new Hub();

        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(true);

        $hub->setSpan($transaction);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($transaction, $hub->getSpan());

        trace(function () use ($transaction, $hub) {
            $this->assertNotSame($transaction, $hub->getSpan());
        }, new SpanContext());

        $this->assertSame($transaction, $hub->getSpan());

        try {
            trace(function () {
                throw new \RuntimeException('Throwing should still restore the previous span');
            }, new SpanContext());
        } catch (\RuntimeException $e) {
            $this->assertSame($transaction, $hub->getSpan());
        }
    }

    public function testTraceDoesntCreateSpanIfTransactionIsNotSampled(): void
    {
        $scope = $this->createMock(Scope::class);

        $hub = new Hub(null, $scope);

        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(false);

        $scope->expects($this->never())
              ->method('setSpan');
        $scope->expects($this->exactly(3))
              ->method('getSpan')
              ->willReturn($transaction);

        SentrySdk::setCurrentHub($hub);

        trace(function () use ($transaction, $hub) {
            $this->assertSame($transaction, $hub->getSpan());
        }, SpanContext::make());

        $this->assertSame($transaction, $hub->getSpan());
    }

    public function testTraceparentWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $scope = new Scope($propagationContext);

        $hub = new Hub(null, $scope);

        SentrySdk::setCurrentHub($hub);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testTraceparentWithTracingEnabled(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1.0,
            ]));

        $hub = new Hub($client);

        SentrySdk::setCurrentHub($hub);

        $spanContext = (new SpanContext())
            ->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'))
            ->setSpanId(new SpanId('566e3688a61d4bc8'));

        $span = new Span($spanContext);

        $hub->setSpan($span);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testW3CTraceparentWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        $scope = new Scope($propagationContext);

        $hub = new Hub(null, $scope);

        SentrySdk::setCurrentHub($hub);

        $traceParent = getW3CTraceparent();

        $this->assertSame('00-566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-00', $traceParent);
    }

    public function testW3CTraceparentWithTracingEnabled(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1.0,
            ]));

        $hub = new Hub($client);

        SentrySdk::setCurrentHub($hub);

        $spanContext = (new SpanContext())
            ->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'))
            ->setSpanId(new SpanId('566e3688a61d4bc8'))
            ->setSampled(true);

        $span = new Span($spanContext);

        $hub->setSpan($span);

        $traceParent = getW3CTraceparent();

        $this->assertSame('00-566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-01', $traceParent);
    }

    public function testBaggageWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSampleRand(0.25);

        $scope = new Scope($propagationContext);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'release' => '1.0.0',
                'environment' => 'development',
            ]));

        $hub = new Hub($client, $scope);

        SentrySdk::setCurrentHub($hub);

        $baggage = getBaggage();

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rand=0.25,sentry-release=1.0.0,sentry-environment=development', $baggage);
    }

    public function testBaggageWithTracingEnabled(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1.0,
                'release' => '1.0.0',
                'environment' => 'development',
            ]));

        $hub = new Hub($client);

        SentrySdk::setCurrentHub($hub);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('Test');
        $transactionContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $transactionContext->getMetadata()->setSampleRand(0.25);

        $transaction = startTransaction($transactionContext);

        $spanContext = new SpanContext();

        $span = $transaction->startChild($spanContext);

        $hub->setSpan($span);

        $baggage = getBaggage();

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1,sentry-transaction=Test,sentry-release=1.0.0,sentry-environment=development,sentry-sampled=true,sentry-sample_rand=0.25', $baggage);
    }

    public function testContinueTrace(): void
    {
        $hub = new Hub();

        SentrySdk::setCurrentHub($hub);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-trace_id=566e3688a61d4bc888951642d6f14a19'
        );

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $transactionContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $transactionContext->getParentSpanId());
        $this->assertTrue($transactionContext->getParentSampled());

        configureScope(function (Scope $scope): void {
            $propagationContext = $scope->getPropagationContext();

            $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $propagationContext->getTraceId());
            $this->assertSame('566e3688a61d4bc8', (string) $propagationContext->getParentSpanId());

            $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();

            $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $dynamicSamplingContext->get('trace_id'));
            $this->assertTrue($dynamicSamplingContext->isFrozen());
        });
    }
}
