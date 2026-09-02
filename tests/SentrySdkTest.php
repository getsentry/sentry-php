<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;

final class SentrySdkTest extends TestCase
{
    public function testInit(): void
    {
        $hub1 = SentrySdk::init();
        $hub2 = SentrySdk::getCurrentHub();

        $this->assertSame($hub1, $hub2);
        $this->assertNotSame(SentrySdk::init(), SentrySdk::init());
    }

    public function testGetCurrentHub(): void
    {
        SentrySdk::init();

        $hub2 = SentrySdk::getCurrentHub();
        $hub3 = SentrySdk::getCurrentHub();

        $this->assertSame($hub2, $hub3);
    }

    public function testSetCurrentHub(): void
    {
        $hub = new Hub();

        $this->assertSame($hub, SentrySdk::setCurrentHub($hub));
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    public function testStartAndEndContextIsolateScopeData(): void
    {
        SentrySdk::init();

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
            $scope->setTag('baseline', 'yes');
        });

        SentrySdk::startContext();

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
            $scope->setTag('request', 'yes');
        });

        SentrySdk::endContext();

        $event = Event::createEvent();

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$event): void {
            $event = $scope->applyToEvent($event);
        });

        $this->assertArrayHasKey('baseline', $event->getTags());
        $this->assertArrayNotHasKey('request', $event->getTags());
    }

    public function testStartContextDoesNotInheritBaselineSpan(): void
    {
        SentrySdk::init();

        $baselineSpan = new Span(new SpanContext());
        SentrySdk::getCurrentHub()->setSpan($baselineSpan);

        SentrySdk::startContext();
        $contextHub = SentrySdk::getCurrentHub();

        $this->assertNull($contextHub->getSpan());

        SentrySdk::endContext();

        $this->assertSame($baselineSpan, SentrySdk::getCurrentHub()->getSpan());
    }

    public function testStartContextCreatesFreshPropagationContext(): void
    {
        SentrySdk::init();

        $globalTraceparent = $this->getCurrentScopeTraceparent();

        SentrySdk::startContext();
        $firstContextTraceparent = $this->getCurrentScopeTraceparent();
        SentrySdk::endContext();

        SentrySdk::startContext();
        $secondContextTraceparent = $this->getCurrentScopeTraceparent();
        SentrySdk::endContext();

        $this->assertNotSame($globalTraceparent, $firstContextTraceparent);
        $this->assertNotSame($firstContextTraceparent, $secondContextTraceparent);
    }

    public function testWithContextResetsSpanAndTransactionAcrossInvocations(): void
    {
        SentrySdk::init();

        SentrySdk::withContext(function (): void {
            $transaction = SentrySdk::getCurrentHub()->startTransaction(new \Sentry\Tracing\TransactionContext('request-1'));
            SentrySdk::getCurrentHub()->setSpan($transaction);

            $this->assertSame($transaction, SentrySdk::getCurrentHub()->getSpan());
            $this->assertSame($transaction, SentrySdk::getCurrentHub()->getTransaction());
        });

        SentrySdk::withContext(function (): void {
            $this->assertNull(SentrySdk::getCurrentHub()->getSpan());
            $this->assertNull(SentrySdk::getCurrentHub()->getTransaction());
        });
    }

    public function testNestedStartContextIsNoOp(): void
    {
        SentrySdk::init();

        $globalHub = SentrySdk::getCurrentHub();

        SentrySdk::startContext();
        $firstContextHub = SentrySdk::getCurrentHub();

        SentrySdk::startContext();
        $secondContextHub = SentrySdk::getCurrentHub();

        $this->assertNotSame($globalHub, $firstContextHub);
        $this->assertSame($firstContextHub, $secondContextHub);

        SentrySdk::endContext();
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());

        SentrySdk::endContext();
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testRuntimeContextStorageIsolatesConcurrentExecutions(): void
    {
        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        $globalHub = SentrySdk::init();

        $storage->switchTo('first');
        SentrySdk::startContext();

        $firstContext = SentrySdk::getCurrentRuntimeContext();
        $firstLogsAggregator = $firstContext->getLogsAggregator();
        $firstMetricsAggregator = $firstContext->getMetricsAggregator();
        $firstHub = new Hub();

        SentrySdk::setCurrentHub($firstHub);

        $this->assertSame($firstHub, $firstContext->getHub());

        $firstHub->configureScope(static function (Scope $scope): void {
            $scope->setTag('execution', 'first');
        });

        $storage->switchTo('second');
        SentrySdk::startContext();

        $secondContext = SentrySdk::getCurrentRuntimeContext();
        $secondHub = $secondContext->getHub();

        $secondHub->configureScope(static function (Scope $scope): void {
            $scope->setTag('execution', 'second');
        });

        $this->assertNotSame($firstContext, $secondContext);
        $this->assertNotSame($firstHub, $secondHub);
        $this->assertNotSame($firstLogsAggregator, $secondContext->getLogsAggregator());
        $this->assertNotSame($firstMetricsAggregator, $secondContext->getMetricsAggregator());

        $storage->switchTo('first');

        $this->assertSame($firstContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame('first', $this->getCurrentScopeTag('execution'));

        $storage->switchTo('second');

        $this->assertSame($secondContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame('second', $this->getCurrentScopeTag('execution'));

        SentrySdk::endContext();

        $this->assertSame($globalHub, SentrySdk::getCurrentHub());

        $storage->switchTo('first');

        $this->assertSame($firstContext, SentrySdk::getCurrentRuntimeContext());

        SentrySdk::endContext();

        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testRuntimeContextStorageCanReleaseAbandonedExecutions(): void
    {
        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        $globalHub = SentrySdk::init();

        $storage->switchTo('abandoned');
        SentrySdk::startContext();

        $abandonedContext = SentrySdk::getCurrentRuntimeContext();

        $storage->release('abandoned');

        $this->assertNotSame($abandonedContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testRepeatedEndContextWithRuntimeContextStorageIsNoOp(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->once())
            ->method('flush')
            ->willReturn(new Result(ResultStatus::success()));

        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        $globalHub = SentrySdk::init();
        $globalHub->bindClient($client);

        $storage->switchTo('request');
        SentrySdk::startContext();
        SentrySdk::endContext();

        $this->assertNull($storage->get());

        SentrySdk::endContext();

        $this->assertNull($storage->get());
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testInitClearsContextStoredByPreviousManager(): void
    {
        /** @var ClientInterface&MockObject $firstClient */
        $firstClient = $this->createMock(ClientInterface::class);
        $firstClient->expects($this->never())
            ->method('flush');

        /** @var ClientInterface&MockObject $secondClient */
        $secondClient = $this->createMock(ClientInterface::class);
        $secondClient->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());
        $secondClient->expects($this->once())
            ->method('flush')
            ->willReturn(new Result(ResultStatus::success()));

        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        SentrySdk::init()->bindClient($firstClient);

        $storage->switchTo('request');
        SentrySdk::startContext();
        $previousHub = SentrySdk::getCurrentHub();

        $freshHub = SentrySdk::init();

        $this->assertNull($storage->get());
        $this->assertNotSame($previousHub, $freshHub);

        $freshHub->bindClient($secondClient);

        SentrySdk::endContext();

        $this->assertNull($storage->get());

        SentrySdk::startContext();

        $this->assertNotNull($storage->get());
        $this->assertSame($secondClient, SentrySdk::getCurrentHub()->getClient());

        SentrySdk::endContext();
    }

    public function testReplacingRuntimeContextStorageDiscardsContextFromPreviousStorage(): void
    {
        $firstStorage = new StubRuntimeContextStorage();
        $secondStorage = new StubRuntimeContextStorage();

        SentrySdk::setRuntimeContextStorage($firstStorage);
        $globalHub = SentrySdk::init();
        SentrySdk::startContext();

        $this->assertNotNull($firstStorage->get());

        SentrySdk::setRuntimeContextStorage($secondStorage);

        $this->assertNull($firstStorage->get());
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());

        SentrySdk::startContext();

        $this->assertNull($firstStorage->get());
        $this->assertSame(SentrySdk::getCurrentRuntimeContext(), $secondStorage->get());

        SentrySdk::endContext();
    }

    public function testUnregisteringRuntimeContextStorageRestoresProcessLocalContext(): void
    {
        $storage = new StubRuntimeContextStorage();

        SentrySdk::setRuntimeContextStorage($storage);
        $globalHub = SentrySdk::init();
        SentrySdk::startContext();

        $this->assertNotNull($storage->get());

        SentrySdk::setRuntimeContextStorage(null);

        $this->assertNull($storage->get());
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());

        SentrySdk::startContext();

        $this->assertNull($storage->get());
        $this->assertNotSame($globalHub, SentrySdk::getCurrentHub());

        SentrySdk::endContext();

        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testEndContextFlushesClientTransportWithOptionalTimeout(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options());
        $client->expects($this->once())
            ->method('flush')
            ->with(12)
            ->willReturn(new Result(ResultStatus::success()));

        SentrySdk::init()->bindClient($client);

        SentrySdk::startContext();
        SentrySdk::endContext(12);
    }

    public function testFlushFlushesClientTransport(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('flush')
            ->with(null)
            ->willReturn(new Result(ResultStatus::success()));

        SentrySdk::init()->bindClient($client);

        SentrySdk::flush();
    }

    public function testEndContextFlushesResourcesIndependently(): void
    {
        StubLogger::$logs = [];

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->atLeastOnce())
            ->method('getOptions')
            ->willReturn(new Options(['logger' => StubLogger::getInstance()]));
        $client->expects($this->exactly(2))
            ->method('captureEvent')
            ->willReturnCallback(static function (Event $event): void {
                throw new \RuntimeException('Failed capturing ' . (string) $event->getType());
            });
        $client->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('Failed flushing transport'));

        SentrySdk::init()->bindClient($client);
        SentrySdk::startContext();

        Logs::getInstance()->info('log');
        TraceMetrics::getInstance()->count('metric', 1);

        SentrySdk::endContext();

        $errors = array_filter(StubLogger::$logs, static function (array $log): bool {
            return $log['level'] === 'error';
        });

        $this->assertSame([
            'Failed to flush logs while ending a runtime context.',
            'Failed to flush trace metrics while ending a runtime context.',
            'Failed to flush the client transport while ending a runtime context.',
        ], array_column($errors, 'message'));
    }

    public function testWithContextReturnsCallbackResultAndRestoresGlobalHub(): void
    {
        SentrySdk::init();

        $globalHub = SentrySdk::getCurrentHub();
        $callbackHub = null;

        $result = SentrySdk::withContext(static function () use (&$callbackHub): string {
            $callbackHub = SentrySdk::getCurrentHub();

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertNotNull($callbackHub);
        $this->assertNotSame($globalHub, $callbackHub);
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testNestedWithContextReusesOuterContext(): void
    {
        SentrySdk::init();

        $globalHub = SentrySdk::getCurrentHub();
        $outerHub = null;
        $innerHub = null;
        $outerContextId = null;
        $innerContextId = null;

        SentrySdk::withContext(function () use (&$outerHub, &$innerHub, &$outerContextId, &$innerContextId, $globalHub): void {
            $outerHub = SentrySdk::getCurrentHub();
            $outerContextId = SentrySdk::getCurrentRuntimeContext()->getId();

            SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope): void {
                $scope->setTag('outer', 'yes');
            });

            SentrySdk::withContext(static function () use (&$innerHub, &$innerContextId): void {
                $innerHub = SentrySdk::getCurrentHub();
                $innerContextId = SentrySdk::getCurrentRuntimeContext()->getId();
            });

            $event = Event::createEvent();

            SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$event): void {
                $event = $scope->applyToEvent($event);
            });

            $this->assertNotSame($globalHub, SentrySdk::getCurrentHub());
            $this->assertSame('yes', $event->getTags()['outer'] ?? null);
            $this->assertSame($outerContextId, SentrySdk::getCurrentRuntimeContext()->getId());
        });

        $this->assertNotNull($outerHub);
        $this->assertNotNull($innerHub);
        $this->assertNotNull($outerContextId);
        $this->assertNotNull($innerContextId);
        $this->assertSame($outerHub, $innerHub);
        $this->assertSame($outerContextId, $innerContextId);
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    public function testWithContextEndsContextWhenCallbackThrows(): void
    {
        SentrySdk::init();

        $globalHub = SentrySdk::getCurrentHub();
        $callbackHub = null;

        try {
            SentrySdk::withContext(static function () use (&$callbackHub): void {
                $callbackHub = SentrySdk::getCurrentHub();

                throw new \RuntimeException('boom');
            });

            $this->fail('The callback exception should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertNotNull($callbackHub);
        $this->assertNotSame($globalHub, $callbackHub);
        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
    }

    private function getCurrentScopeTraceparent(): string
    {
        $traceparent = '';

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use (&$traceparent): void {
            $traceparent = $scope->getPropagationContext()->toTraceparent();
        });

        return $traceparent;
    }

    private function getCurrentScopeTag(string $key): ?string
    {
        $value = null;

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use ($key, &$value): void {
            $event = $scope->applyToEvent(Event::createEvent());
            $value = $event !== null ? $event->getTags()[$key] ?? null : null;
        });

        return $value;
    }
}
