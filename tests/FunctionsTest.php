<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
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

        $this->assertNotNull(SentrySdk::getGlobalScope()->getClient());
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $functionCallArgs): void
    {
        $eventId = EventId::generate();

        $message = $functionCallArgs[0];
        $level = $functionCallArgs[1] ?? null;
        $hint = $functionCallArgs[2] ?? null;

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with(
                $message,
                $level,
                $this->callback(function (Scope $scope): bool {
                    return $scope instanceof Scope;
                }),
                $hint
            )
            ->willReturn($eventId);

        SentrySdk::init($client);

        $this->assertSame($eventId, captureMessage(...$functionCallArgs));
    }

    public static function captureMessageDataProvider(): \Generator
    {
        yield [
            [
                'foo',
                Severity::debug(),
            ],
        ];

        yield [
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
    public function testCaptureException(array $functionCallArgs): void
    {
        $eventId = EventId::generate();

        $exception = $functionCallArgs[0];
        $hint = $functionCallArgs[1] ?? null;

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with(
                $exception,
                $this->callback(function (Scope $scope): bool {
                    return $scope instanceof Scope;
                }),
                $hint
            )
            ->willReturn($eventId);

        SentrySdk::init($client);

        $this->assertSame($eventId, captureException(...$functionCallArgs));
    }

    public static function captureExceptionDataProvider(): \Generator
    {
        yield [
            [
                new \Exception('foo'),
            ],
        ];

        yield [
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

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(
                $event,
                $hint,
                $this->callback(function (Scope $scope): bool {
                    return $scope instanceof Scope;
                })
            )
            ->willReturn($event->getId());

        SentrySdk::init($client);

        $this->assertSame($event->getId(), captureEvent($event, $hint));
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $functionCallArgs): void
    {
        $eventId = EventId::generate();

        $hint = $functionCallArgs[0] ?? null;

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError')
            ->with(
                $this->callback(function (Scope $scope): bool {
                    return $scope instanceof Scope;
                }),
                $hint
            )
            ->willReturn($eventId);

        SentrySdk::init($client);

        @trigger_error('foo', \E_USER_NOTICE);

        $this->assertSame($eventId, captureLastError(...$functionCallArgs));
    }

    public static function captureLastErrorDataProvider(): \Generator
    {
        yield [
            [],
        ];

        yield [
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

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureCheckIn')
            ->with(
                'test-crontab',
                CheckInStatus::ok(),
                10,
                $monitorConfig,
                $checkInId
            )
            ->willReturn($checkInId);

        SentrySdk::init($client);

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
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
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

        SentrySdk::init($client);

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

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('captureCheckIn')
            ->with(
                $this->callback(function (string $slug): bool {
                    return $slug === 'test-crontab';
                }),
                $this->callback(function (CheckInStatus $checkInStatus): bool {
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

        SentrySdk::init($client);

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

        SentrySdk::init(new NoOpClient());

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

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sample_rate' => 1.0,
            ]));

        SentrySdk::init($client);

        $transaction = startTransaction($transactionContext, $customSamplingContext);

        $this->assertSame('foo', $transaction->getName());
        $this->assertTrue($transaction->getSampled());
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
        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(true);

        SentrySdk::init(new NoOpClient());
        SentrySdk::getCurrentScope()->setSpan($transaction);

        $this->assertSame($transaction, SentrySdk::getCurrentScope()->getSpan());

        trace(function () use ($transaction) {
            $this->assertNotSame($transaction, SentrySdk::getCurrentScope()->getSpan());
        }, new SpanContext());

        $this->assertSame($transaction, SentrySdk::getCurrentScope()->getSpan());

        try {
            trace(function () {
                throw new \RuntimeException('Throwing should still restore the previous span');
            }, new SpanContext());
        } catch (\RuntimeException $e) {
            $this->assertSame($transaction, SentrySdk::getCurrentScope()->getSpan());
        }
    }

    public function testTraceDoesntCreateSpanIfTransactionIsNotSampled(): void
    {
        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(false);

        SentrySdk::init(new NoOpClient());
        SentrySdk::getCurrentScope()->setSpan($transaction);

        trace(function () use ($transaction) {
            $this->assertSame($transaction, SentrySdk::getCurrentScope()->getSpan());
        }, SpanContext::make());

        $this->assertSame($transaction, SentrySdk::getCurrentScope()->getSpan());
    }

    public function testTraceparentWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        SentrySdk::init(new NoOpClient());
        SentrySdk::getIsolationScope()->setPropagationContext($propagationContext);

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

        SentrySdk::init($client);

        $spanContext = (new SpanContext())
            ->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'))
            ->setSpanId(new SpanId('566e3688a61d4bc8'));

        $span = new Span($spanContext);

        SentrySdk::getCurrentScope()->setSpan($span);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testBaggageWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSampleRand(0.25);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options([
                'release' => '1.0.0',
                'environment' => 'development',
            ]));

        SentrySdk::init($client);
        SentrySdk::getIsolationScope()->setPropagationContext($propagationContext);

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

        SentrySdk::init($client);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('Test');
        $transactionContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $transactionContext->getMetadata()->setSampleRand(0.25);

        $transaction = SentrySdk::startTransaction($transactionContext);

        $spanContext = new SpanContext();

        $span = $transaction->startChild($spanContext);

        SentrySdk::getCurrentScope()->setSpan($span);

        $baggage = getBaggage();

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1,sentry-transaction=Test,sentry-release=1.0.0,sentry-environment=development,sentry-sampled=true,sentry-sample_rand=0.25', $baggage);
    }

    public function testContinueTrace(): void
    {
        SentrySdk::init(new NoOpClient());

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-trace_id=566e3688a61d4bc888951642d6f14a19'
        );

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $transactionContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $transactionContext->getParentSpanId());
        $this->assertTrue($transactionContext->getParentSampled());

        $propagationContext = SentrySdk::getIsolationScope()->getPropagationContext();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $propagationContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $propagationContext->getParentSpanId());

        $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $dynamicSamplingContext->get('trace_id'));
        $this->assertTrue($dynamicSamplingContext->isFrozen());
    }
}
