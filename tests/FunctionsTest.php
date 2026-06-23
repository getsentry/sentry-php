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
use Sentry\Integration\OTLPIntegration;
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
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Util\SentryUid;

use function Sentry\addBreadcrumb;
use function Sentry\addFeatureFlag;
use function Sentry\captureCheckIn;
use function Sentry\captureEvent;
use function Sentry\captureException;
use function Sentry\captureLastError;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\continueTrace;
use function Sentry\endContext;
use function Sentry\getBaggage;
use function Sentry\getOtlpTracesEndpointUrl;
use function Sentry\getTraceparent;
use function Sentry\init;
use function Sentry\startContext;
use function Sentry\startTransaction;
use function Sentry\trace;
use function Sentry\withContext;
use function Sentry\withMonitor;
use function Sentry\withScope;

final class FunctionsTest extends TestCase
{
    public function testInit(): void
    {
        init(['default_integrations' => false]);

        $client = SentrySdk::getClient();

        $this->assertNotInstanceOf(NoOpClient::class, $client);
        $this->assertSame($client, SentrySdk::getGlobalScope()->getClient());
    }

    public function testInitPreservesGlobalScope(): void
    {
        $globalScope = SentrySdk::getGlobalScope();
        $globalScope->setTag('baseline', 'yes');

        init(['default_integrations' => false]);

        $this->assertSame($globalScope, SentrySdk::getGlobalScope());
        $this->assertSame(SentrySdk::getClient(), $globalScope->getClient());

        $event = $globalScope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['baseline' => 'yes'], $event->getTags());
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();
        $message = $expectedFunctionCallArgs[0];
        $level = $expectedFunctionCallArgs[1];
        $hint = $expectedFunctionCallArgs[2];

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureMessage')
            ->with($message, $level, $this->captureScopeConstraint($scope), $hint)
            ->willReturn($eventId);

        $this->assertSame($eventId, captureMessage(...$functionCallArgs));
        $this->assertSame($eventId, $scope->getLastEventId());
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

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureException')
            ->with($expectedFunctionCallArgs[0], $this->captureScopeConstraint($scope), $expectedFunctionCallArgs[1])
            ->willReturn($eventId);

        $this->assertSame($eventId, captureException(...$functionCallArgs));
        $this->assertSame($eventId, $scope->getLastEventId());
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

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint, $this->captureScopeConstraint($scope))
            ->willReturn($event->getId());

        $this->assertSame($event->getId(), captureEvent($event, $hint));
        $this->assertSame($event->getId(), $scope->getLastEventId());
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('captureLastError')
            ->with($this->captureScopeConstraint($scope), $expectedFunctionCallArgs[0])
            ->willReturn($eventId);

        @trigger_error('foo', \E_USER_NOTICE);

        $this->assertSame($eventId, captureLastError(...$functionCallArgs));
        $this->assertSame($eventId, $scope->getLastEventId());
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

    public function testCaptureMessageClearsLastEventIdWhenClientReturnsNull(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);
        $scope->setLastEventId(EventId::generate());

        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', null, $this->captureScopeConstraint($scope), null)
            ->willReturn(null);

        $this->assertNull(captureMessage('foo'));
        $this->assertNull($scope->getLastEventId());
    }

    public function testCaptureExceptionClearsLastEventIdWhenClientReturnsNull(): void
    {
        $exception = new \RuntimeException('foo');
        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);
        $scope->setLastEventId(EventId::generate());

        $client->expects($this->once())
            ->method('captureException')
            ->with($exception, $this->captureScopeConstraint($scope), null)
            ->willReturn(null);

        $this->assertNull(captureException($exception));
        $this->assertNull($scope->getLastEventId());
    }

    public function testCaptureEventClearsLastEventIdWhenClientReturnsNull(): void
    {
        $event = Event::createEvent();
        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);
        $scope->setLastEventId(EventId::generate());

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event, null, $this->captureScopeConstraint($scope))
            ->willReturn(null);

        $this->assertNull(captureEvent($event));
        $this->assertNull($scope->getLastEventId());
    }

    public function testCaptureLastErrorClearsLastEventIdWhenClientReturnsNull(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);
        $scope->setLastEventId(EventId::generate());

        $client->expects($this->once())
            ->method('captureLastError')
            ->with($this->captureScopeConstraint($scope), null)
            ->willReturn(null);

        $this->assertNull(captureLastError());
        $this->assertNull($scope->getLastEventId());
    }

    public function testCaptureCheckIn(): void
    {
        $checkInId = SentryUid::generate();
        $eventId = EventId::generate();
        $monitorConfig = new MonitorConfig(
            MonitorSchedule::crontab('*/5 * * * *'),
            5,
            30,
            'UTC'
        );

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'environment' => Event::DEFAULT_ENVIRONMENT,
                'release' => '1.0.0',
            ]));
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($checkInId, $monitorConfig): bool {
                $checkIn = $event->getCheckIn();

                return $checkIn !== null
                    && $checkIn->getId() === $checkInId
                    && $checkIn->getMonitorSlug() === 'test-crontab'
                    && $checkIn->getStatus() == CheckInStatus::ok()
                    && $checkIn->getRelease() === '1.0.0'
                    && $checkIn->getEnvironment() === Event::DEFAULT_ENVIRONMENT
                    && $checkIn->getDuration() === 10
                    && $checkIn->getMonitorConfig() === $monitorConfig;
            }), null, $this->captureScopeConstraint($scope))
            ->willReturn($eventId);

        $this->assertSame($checkInId, captureCheckIn(
            'test-crontab',
            CheckInStatus::ok(),
            10,
            $monitorConfig,
            $checkInId
        ));
        $this->assertSame($eventId, $scope->getLastEventId());
    }

    public function testCaptureCheckInReturnsNullForNoOpClient(): void
    {
        SentrySdk::init(new NoOpClient());

        $this->assertNull(captureCheckIn('test-crontab', CheckInStatus::ok()));
    }

    public function testWithMonitor(): void
    {
        $events = [];
        $monitorConfig = new MonitorConfig(
            new MonitorSchedule(MonitorSchedule::TYPE_CRONTAB, '*/5 * * * *'),
            5,
            30,
            'UTC'
        );

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->exactly(2))
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event): bool {
                $checkIn = $event->getCheckIn();

                return $checkIn !== null
                    && $checkIn->getMonitorSlug() === 'test-crontab'
                    && $checkIn->getMonitorConfig() !== null
                    && $checkIn->getMonitorConfig()->getSchedule()->getValue() === '*/5 * * * *';
            }), null, $this->captureScopeConstraint($scope))
            ->willReturnCallback(static function (Event $event, ?EventHint $hint = null, ?Scope $scope = null) use (&$events): EventId {
                $events[] = $event;

                return EventId::generate();
            });

        $result = withMonitor('test-crontab', static function (): string {
            // Do something...
            return 'done';
        }, $monitorConfig);

        $this->assertSame('done', $result);
        $this->assertCount(2, $events);
        $this->assertSame(CheckInStatus::inProgress(), $events[0]->getCheckIn()->getStatus());
        $this->assertSame(CheckInStatus::ok(), $events[1]->getCheckIn()->getStatus());
        $this->assertSame($events[0]->getCheckIn()->getId(), $events[1]->getCheckIn()->getId());
    }

    public function testWithMonitorCallableThrows(): void
    {
        $events = [];

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->exactly(2))
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->exactly(2))
            ->method('captureEvent')
            ->with($this->isInstanceOf(Event::class), null, $this->captureScopeConstraint($scope))
            ->willReturnCallback(static function (Event $event, ?EventHint $hint = null, ?Scope $scope = null) use (&$events): EventId {
                $events[] = $event;

                return EventId::generate();
            });

        try {
            withMonitor('test-crontab', static function (): void {
                throw new \Exception('monitor failed');
            }, new MonitorConfig(
                new MonitorSchedule(MonitorSchedule::TYPE_CRONTAB, '*/5 * * * *'),
                5,
                30,
                'UTC'
            ));

            $this->fail('The callback exception should be rethrown.');
        } catch (\Exception $exception) {
            $this->assertSame('monitor failed', $exception->getMessage());
        }

        $this->assertCount(2, $events);
        $this->assertSame(CheckInStatus::inProgress(), $events[0]->getCheckIn()->getStatus());
        $this->assertSame(CheckInStatus::error(), $events[1]->getCheckIn()->getStatus());
        $this->assertSame($events[0]->getCheckIn()->getId(), $events[1]->getCheckIn()->getId());
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $otherScope = new Scope();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['default_integrations' => false]));

        addBreadcrumb($breadcrumb);

        $this->assertScopeBreadcrumbs($scope, [$breadcrumb]);
        $this->assertScopeBreadcrumbs($otherScope, []);
    }

    public function testAddBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsZero(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['max_breadcrumbs' => 0]));

        addBreadcrumb($breadcrumb);

        $this->assertScopeBreadcrumbs($scope, []);
    }

    public function testAddBreadcrumbDoesNothingForNoOpClient(): void
    {
        SentrySdk::init(new NoOpClient());
        $scope = SentrySdk::getIsolationScope();

        addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $this->assertScopeBreadcrumbs($scope, []);
    }

    public function testAddBreadcrumbDoesNothingWhenBeforeBreadcrumbCallbackReturnsNull(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'before_breadcrumb' => static function () {
                    return null;
                },
            ]));

        addBreadcrumb($breadcrumb);

        $this->assertScopeBreadcrumbs($scope, []);
    }

    public function testAddBreadcrumbStoresBreadcrumbReturnedByBeforeBreadcrumbCallback(): void
    {
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_DEFAULT, 'custom');

        $client = $this->createMock(ClientInterface::class);
        $scope = $this->setClientAndIsolationScope($client);

        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'before_breadcrumb' => static function () use ($breadcrumb2): Breadcrumb {
                    return $breadcrumb2;
                },
            ]));

        addBreadcrumb($breadcrumb1);

        $this->assertScopeBreadcrumbs($scope, [$breadcrumb2]);
    }

    public function testWithScope(): void
    {
        $returnValue = withScope(static function (): string {
            return 'foobarbaz';
        });

        $this->assertSame('foobarbaz', $returnValue);
    }

    public function testConfigureScopeMutatesCurrentIsolationScopeOnly(): void
    {
        $globalScope = SentrySdk::getGlobalScope();
        $globalScope->setTag('scope', 'global');

        $isolationScope = new Scope();
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($isolationScope);

        $callbackScope = null;

        configureScope(static function (Scope $scope) use (&$callbackScope): void {
            $callbackScope = $scope;
            $scope->setTag('scope', 'isolation');
        });

        $this->assertSame($isolationScope, $callbackScope);

        $isolationEvent = $isolationScope->applyToEvent(Event::createEvent());
        $this->assertNotNull($isolationEvent);
        $this->assertSame(['scope' => 'isolation'], $isolationEvent->getTags());

        $globalEvent = $globalScope->applyToEvent(Event::createEvent());
        $this->assertNotNull($globalEvent);
        $this->assertSame(['scope' => 'global'], $globalEvent->getTags());
    }

    public function testAddFeatureFlagMutatesCurrentIsolationScopeOnly(): void
    {
        $globalScope = SentrySdk::getGlobalScope();
        $globalScope->addFeatureFlag('global-only', true);

        $isolationScope = new Scope();
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($isolationScope);

        addFeatureFlag('isolation-only', false);

        $isolationEvent = $isolationScope->applyToEvent(Event::createEvent());
        $this->assertNotNull($isolationEvent);
        $this->assertSame([
            'values' => [
                [
                    'flag' => 'isolation-only',
                    'result' => false,
                ],
            ],
        ], $isolationEvent->getContexts()['flags']);

        $globalEvent = $globalScope->applyToEvent(Event::createEvent());
        $this->assertNotNull($globalEvent);
        $this->assertSame([
            'values' => [
                [
                    'flag' => 'global-only',
                    'result' => true,
                ],
            ],
        ], $globalEvent->getContexts()['flags']);
    }

    public function testStartAndEndContext(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        startContext();

        $requestScope = SentrySdk::getIsolationScope();

        $this->assertNotSame($globalScope, $requestScope);

        endContext();

        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testWithContext(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        $result = withContext(function () use ($globalScope): string {
            $this->assertNotSame($globalScope, SentrySdk::getIsolationScope());

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testNestedWithContextReusesOuterContext(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();
        $outerScope = null;
        $innerScope = null;

        withContext(function () use (&$outerScope, &$innerScope, $globalScope): void {
            $outerScope = SentrySdk::getIsolationScope();

            configureScope(static function (Scope $scope): void {
                $scope->setTag('outer', 'yes');
            });

            withContext(static function () use (&$innerScope): void {
                $innerScope = SentrySdk::getIsolationScope();
            });

            $event = Event::createEvent();

            configureScope(static function (Scope $scope) use (&$event): void {
                $event = $scope->applyToEvent($event);
            });

            $this->assertNotSame($globalScope, SentrySdk::getIsolationScope());
            $this->assertSame('yes', $event->getTags()['outer'] ?? null);
        });

        $this->assertNotNull($outerScope);
        $this->assertNotNull($innerScope);
        $this->assertSame($outerScope, $innerScope);
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testWithContextAlwaysEndsContextWithOptionalTimeout(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->once())
            ->method('flush')
            ->with(13)
            ->willReturn(new Result(ResultStatus::success()));

        SentrySdk::init($client);

        try {
            withContext(static function (): void {
                throw new \RuntimeException('callback failed');
            }, 13);

            $this->fail('The callback exception should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('callback failed', $exception->getMessage());
        }
    }

    public function testStartTransaction(): void
    {
        $transactionContext = new TransactionContext('foo');
        $transaction = new Transaction($transactionContext);
        $customSamplingContext = ['foo' => 'bar'];

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        SentrySdk::getGlobalScope()->setClient($client);

        $transaction = startTransaction($transactionContext, $customSamplingContext);

        $this->assertSame('foo', $transaction->getName());
    }

    public function testTraceReturnsClosureResult(): void
    {
        $returnValue = 'foo';

        $result = trace(static function () use ($returnValue) {
            return $returnValue;
        }, new SpanContext());

        $this->assertSame($returnValue, $result);
    }

    public function testTraceCorrectlyReplacesAndRestoresCurrentSpan(): void
    {
        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(true);
        $outerScope = SentrySdk::getIsolationScope();
        $outerScope->setSpan($transaction);

        $this->assertSame($transaction, SentrySdk::getIsolationScope()->getSpan());

        $childSpan = null;

        trace(function (Scope $scope) use ($outerScope, $transaction, &$childSpan): void {
            $childSpan = $scope->getSpan();

            $this->assertNotSame($outerScope, $scope);
            $this->assertNotSame($transaction, $childSpan);
            $this->assertSame($childSpan, SentrySdk::getIsolationScope()->getSpan());
            $this->assertNull($childSpan->getEndTimestamp());
        }, new SpanContext());

        $this->assertNotNull($childSpan);
        $this->assertNotNull($childSpan->getEndTimestamp());
        $this->assertSame($outerScope, SentrySdk::getIsolationScope());
        $this->assertSame($transaction, SentrySdk::getIsolationScope()->getSpan());

        try {
            trace(function (Scope $scope) use ($transaction): void {
                $this->assertNotSame($transaction, $scope->getSpan());

                throw new \RuntimeException('Throwing should still restore the previous span');
            }, new SpanContext());
        } catch (\RuntimeException $e) {
            $this->assertSame($outerScope, SentrySdk::getIsolationScope());
            $this->assertSame($transaction, SentrySdk::getIsolationScope()->getSpan());
        }
    }

    public function testTraceDoesntCreateSpanIfTransactionIsNotSampled(): void
    {
        $transaction = new Transaction(TransactionContext::make());
        $transaction->setSampled(false);

        $outerScope = SentrySdk::getIsolationScope();
        $outerScope->setSpan($transaction);
        $callbackScope = null;

        trace(function (Scope $scope) use ($transaction, &$callbackScope): void {
            $callbackScope = $scope;

            $this->assertSame($transaction, $scope->getSpan());
            $this->assertSame($transaction, SentrySdk::getIsolationScope()->getSpan());
        }, SpanContext::make());

        $this->assertNotSame($outerScope, $callbackScope);
        $this->assertSame($outerScope, SentrySdk::getIsolationScope());
        $this->assertSame($transaction, SentrySdk::getIsolationScope()->getSpan());
    }

    public function testTraceparentWithTracingDisabled(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        SentrySdk::getCurrentRuntimeContext()->setIsolationScope(new Scope($propagationContext));

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

        SentrySdk::getGlobalScope()->setClient($client);

        $spanContext = (new SpanContext())
            ->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'))
            ->setSpanId(new SpanId('566e3688a61d4bc8'));

        $span = new Span($spanContext);

        SentrySdk::getIsolationScope()->setSpan($span);

        $traceParent = getTraceparent();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8', $traceParent);
    }

    public function testTraceHeadersAreEmptyWhenExternalPropagationContextIsActive(): void
    {
        $propagationContext = PropagationContext::fromDefaults();
        $propagationContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $propagationContext->setSpanId(new SpanId('566e3688a61d4bc8'));

        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        SentrySdk::getCurrentRuntimeContext()->setIsolationScope(new Scope($propagationContext));

        $this->assertSame('', getTraceparent());
        $this->assertSame('', getBaggage());

        Scope::clearExternalPropagationContext();
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

        SentrySdk::getGlobalScope()->setClient($client);
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope(new Scope($propagationContext));

        $baggage = getBaggage();

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rand=0.25,sentry-release=1.0.0,sentry-environment=development', $baggage);
        $this->assertNotNull($propagationContext->getDynamicSamplingContext());
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

        SentrySdk::getGlobalScope()->setClient($client);

        $transactionContext = new TransactionContext();
        $transactionContext->setName('Test');
        $transactionContext->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $transactionContext->getMetadata()->setSampleRand(0.25);

        $transaction = startTransaction($transactionContext);

        $spanContext = new SpanContext();

        $span = $transaction->startChild($spanContext);

        SentrySdk::getIsolationScope()->setSpan($span);

        $baggage = getBaggage();

        $this->assertSame('sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1,sentry-transaction=Test,sentry-release=1.0.0,sentry-environment=development,sentry-sampled=true,sentry-sample_rand=0.25', $baggage);
    }

    public function testGetOtlpTracesEndpointUrlFallsBackToDsn(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(OTLPIntegration::class)
            ->willReturn(null);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'https://public@example.com/1',
            ]));

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame('https://example.com/api/1/integration/otlp/v1/traces/', getOtlpTracesEndpointUrl());
    }

    public function testGetOtlpTracesEndpointUrlPrefersCollectorUrl(): void
    {
        $integration = new OTLPIntegration(false, 'http://collector:4318/v1/traces');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(OTLPIntegration::class)
            ->willReturn($integration);
        $client->method('getOptions')
            ->willReturn(new Options([
                'dsn' => 'https://public@example.com/1',
            ]));

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame('http://collector:4318/v1/traces', getOtlpTracesEndpointUrl());
    }

    public function testContinueTrace(): void
    {
        SentrySdk::getGlobalScope()->setClient(new NoOpClient());

        $scope = new Scope();
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($scope);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-trace_id=566e3688a61d4bc888951642d6f14a19'
        );

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $transactionContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $transactionContext->getParentSpanId());
        $this->assertTrue($transactionContext->getParentSampled());

        $propagationContext = $scope->getPropagationContext();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $propagationContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $propagationContext->getParentSpanId());

        $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $dynamicSamplingContext->get('trace_id'));
        $this->assertTrue($dynamicSamplingContext->isFrozen());
    }

    public function testContinueTraceWhenOrgMismatch(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]));

        SentrySdk::getGlobalScope()->setClient($client);

        $scope = new Scope();
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($scope);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-org_id=2'
        );

        $newTraceId = (string) $transactionContext->getTraceId();
        $newSampleRand = $transactionContext->getMetadata()->getSampleRand();

        $this->assertNotSame('566e3688a61d4bc888951642d6f14a19', $newTraceId);
        $this->assertNotEmpty($newTraceId);
        $this->assertNull($transactionContext->getParentSpanId());
        $this->assertNull($transactionContext->getParentSampled());
        $this->assertNull($transactionContext->getMetadata()->getDynamicSamplingContext());
        $this->assertNotNull($newSampleRand);

        $propagationContext = $scope->getPropagationContext();

        $this->assertSame($newTraceId, (string) $propagationContext->getTraceId());
        $this->assertNull($propagationContext->getParentSpanId());
        $this->assertNull($propagationContext->getDynamicSamplingContext());
        $this->assertSame($newSampleRand, $propagationContext->getSampleRand());
    }

    public function testContinueTraceWhenOrgMatch(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'strict_trace_continuation' => true,
                'org_id' => 1,
            ]));

        SentrySdk::getGlobalScope()->setClient($client);

        $scope = new Scope();
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($scope);

        $transactionContext = continueTrace(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            'sentry-org_id=1'
        );

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $transactionContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $transactionContext->getParentSpanId());
        $this->assertTrue($transactionContext->getParentSampled());

        $propagationContext = $scope->getPropagationContext();

        $this->assertSame('566e3688a61d4bc888951642d6f14a19', (string) $propagationContext->getTraceId());
        $this->assertSame('566e3688a61d4bc8', (string) $propagationContext->getParentSpanId());

        $dynamicSamplingContext = $propagationContext->getDynamicSamplingContext();

        $this->assertNotNull($dynamicSamplingContext);
        $this->assertSame('1', $dynamicSamplingContext->get('org_id'));
    }

    private function setClientAndIsolationScope(ClientInterface $client): Scope
    {
        SentrySdk::init();

        SentrySdk::getGlobalScope()->clear();
        SentrySdk::getGlobalScope()->setTag('scope', 'global');
        SentrySdk::getGlobalScope()->setTag('global', 'yes');
        SentrySdk::getGlobalScope()->setClient($client);

        $scope = new Scope();
        $scope->setTag('scope', 'isolation');
        $scope->setTag('isolation', 'yes');
        SentrySdk::getCurrentRuntimeContext()->setIsolationScope($scope);

        return $scope;
    }

    private function captureScopeConstraint(Scope $isolationScope)
    {
        return $this->callback(function (Scope $captureScope) use ($isolationScope): bool {
            $this->assertNotSame($isolationScope, $captureScope);

            $event = $captureScope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);
            $this->assertSame([
                'scope' => 'isolation',
                'global' => 'yes',
                'isolation' => 'yes',
            ], $event->getTags());

            return true;
        });
    }

    /**
     * @param Breadcrumb[] $expectedBreadcrumbs
     */
    private function assertScopeBreadcrumbs(Scope $scope, array $expectedBreadcrumbs): void
    {
        $event = $scope->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame($expectedBreadcrumbs, $event->getBreadcrumbs());
    }
}
