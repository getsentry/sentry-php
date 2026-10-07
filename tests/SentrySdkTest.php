<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\IsolationScope;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;

use function Sentry\startTransaction;

final class SentrySdkTest extends TestCase
{
    public function testInitResetsRuntimeContext(): void
    {
        $previousScope = SentrySdk::getIsolationScope();
        $previousScope->setTag('runtime', 'old');

        SentrySdk::init();

        $currentScope = SentrySdk::getIsolationScope();

        $this->assertNotSame($previousScope, $currentScope);

        $event = SentrySdk::getGlobalScope()->merge($currentScope)->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame([], $event->getTags());
    }

    public function testGetGlobalScope(): void
    {
        $scope = SentrySdk::getGlobalScope();

        $this->assertSame($scope, SentrySdk::getGlobalScope());
    }

    public function testGetIsolationScope(): void
    {
        $scope = SentrySdk::getIsolationScope();

        $this->assertSame($scope, SentrySdk::getIsolationScope());
    }

    public function testGetClientReturnsCachedNoOpFallbackBeforeInit(): void
    {
        $client = SentrySdk::getClient();

        $this->assertInstanceOf(NoOpClient::class, $client);
        $this->assertSame($client, SentrySdk::getClient());
    }

    public function testGetClientReturnsGlobalScopeClient(): void
    {
        $client = $this->createMock(ClientInterface::class);

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($client, SentrySdk::getClient());
    }

    public function testGetClientReturnsIsolationScopeClientBeforeGlobalScopeClient(): void
    {
        $globalClient = $this->createMock(ClientInterface::class);
        $isolationClient = $this->createMock(ClientInterface::class);

        SentrySdk::getGlobalScope()->setClient($globalClient);
        SentrySdk::getIsolationScope()->setClient($isolationClient);

        $this->assertSame($isolationClient, SentrySdk::getClient());
    }

    public function testStartContextUsesSeparateIsolationScope(): void
    {
        $globalIsolationScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext();

        $contextIsolationScope = SentrySdk::getIsolationScope();

        $this->assertNotSame($globalIsolationScope, $contextIsolationScope);

        SentrySdk::endContext();

        $this->assertSame($globalIsolationScope, SentrySdk::getIsolationScope());
    }

    public function testInitWithClientSetsGlobalScopeClient(): void
    {
        $client = $this->createMock(ClientInterface::class);

        SentrySdk::init($client);

        $this->assertSame($client, SentrySdk::getClient());
    }

    public function testInitDoesNotResetGlobalScope(): void
    {
        $globalScope = SentrySdk::getGlobalScope();
        $globalScope->setTag('baseline', 'yes');

        SentrySdk::init();

        $this->assertSame($globalScope, SentrySdk::getGlobalScope());

        $event = $globalScope->merge(new IsolationScope())->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['baseline' => 'yes'], $event->getTags());
    }

    public function testInitKeepsGlobalScopeEventProcessors(): void
    {
        $processorCalled = false;

        SentrySdk::getGlobalScope()->addEventProcessor(static function (Event $event) use (&$processorCalled): Event {
            $processorCalled = true;

            return $event;
        });

        SentrySdk::init();

        $event = SentrySdk::getGlobalScope()->merge(new IsolationScope())->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertTrue($processorCalled);
    }

    public function testStartAndEndContextIsolateScopeData(): void
    {
        SentrySdk::init();

        SentrySdk::getIsolationScope()->setTag('baseline', 'yes');

        SentrySdk::startContext();

        SentrySdk::getIsolationScope()->setTag('request', 'yes');

        SentrySdk::endContext();

        $event = Event::createEvent();
        $event = SentrySdk::getGlobalScope()->merge(SentrySdk::getIsolationScope())->applyToEvent($event);

        $this->assertArrayHasKey('baseline', $event->getTags());
        $this->assertArrayNotHasKey('request', $event->getTags());
    }

    public function testStartContextDoesNotInheritBaselineSpan(): void
    {
        SentrySdk::init();

        $baselineSpan = new Span(new SpanContext());
        SentrySdk::getIsolationScope()->setSpan($baselineSpan);

        SentrySdk::startContext();

        $this->assertNull(SentrySdk::getIsolationScope()->getSpan());

        SentrySdk::endContext();

        $this->assertSame($baselineSpan, SentrySdk::getIsolationScope()->getSpan());
    }

    public function testStartContextUsesProvidedIsolationScopeAsIs(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();
        $span = new Span(new SpanContext());
        $isolationScope = new IsolationScope();
        $isolationScope->setSpan($span);
        $traceparent = $isolationScope->getPropagationContext()->toTraceparent();

        SentrySdk::startContext($isolationScope);

        $this->assertSame($isolationScope, SentrySdk::getIsolationScope());
        $this->assertSame($span, SentrySdk::getIsolationScope()->getSpan());
        $this->assertSame($traceparent, $this->getCurrentScopeTraceparent());

        SentrySdk::endContext();

        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
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
            $transaction = startTransaction(new TransactionContext('request-1'));
            SentrySdk::getIsolationScope()->setSpan($transaction);

            $this->assertSame($transaction, SentrySdk::getIsolationScope()->getSpan());
            $this->assertSame($transaction, SentrySdk::getIsolationScope()->getTransaction());
        });

        SentrySdk::withContext(function (): void {
            $this->assertNull(SentrySdk::getIsolationScope()->getSpan());
            $this->assertNull(SentrySdk::getIsolationScope()->getTransaction());
        });
    }

    public function testNestedStartContextIsNoOp(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext();
        $firstContextScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext();
        $secondContextScope = SentrySdk::getIsolationScope();

        $this->assertNotSame($globalScope, $firstContextScope);
        $this->assertSame($firstContextScope, $secondContextScope);

        SentrySdk::endContext();
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());

        SentrySdk::endContext();
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testNestedStartContextIgnoresProvidedIsolationScope(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext();
        $contextScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext(new IsolationScope());

        $this->assertSame($contextScope, SentrySdk::getIsolationScope());

        SentrySdk::endContext();

        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testRuntimeContextStorageIsolatesConcurrentExecutions(): void
    {
        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        $storage->switchTo('first');

        $firstScope = new IsolationScope();
        SentrySdk::startContext($firstScope);

        $firstContext = SentrySdk::getCurrentRuntimeContext();
        $firstLogsAggregator = $firstContext->getLogsAggregator();
        $firstMetricsAggregator = $firstContext->getMetricsAggregator();

        $this->assertSame($firstScope, $firstContext->getIsolationScope());

        SentrySdk::getIsolationScope()->setTag('execution', 'first');

        $storage->switchTo('second');
        SentrySdk::startContext();

        $secondContext = SentrySdk::getCurrentRuntimeContext();
        $secondScope = $secondContext->getIsolationScope();

        SentrySdk::getIsolationScope()->setTag('execution', 'second');

        $this->assertNotSame($firstContext, $secondContext);
        $this->assertNotSame($firstScope, $secondScope);
        $this->assertNotSame($firstLogsAggregator, $secondContext->getLogsAggregator());
        $this->assertNotSame($firstMetricsAggregator, $secondContext->getMetricsAggregator());

        $storage->switchTo('first');

        $this->assertSame($firstContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame('first', $this->getCurrentScopeTag('execution'));

        $storage->switchTo('second');

        $this->assertSame($secondContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame('second', $this->getCurrentScopeTag('execution'));

        SentrySdk::endContext();

        $this->assertSame($globalScope, SentrySdk::getIsolationScope());

        $storage->switchTo('first');

        $this->assertSame($firstContext, SentrySdk::getCurrentRuntimeContext());

        SentrySdk::endContext();

        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
        $this->assertNull($this->getCurrentScopeTag('execution'));
    }

    public function testRuntimeContextStorageCanReleaseAbandonedExecutions(): void
    {
        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        $storage->switchTo('abandoned');
        SentrySdk::startContext();

        $abandonedContext = SentrySdk::getCurrentRuntimeContext();

        $storage->release('abandoned');

        $this->assertNotSame($abandonedContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
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
        SentrySdk::init($client);

        $globalScope = SentrySdk::getIsolationScope();

        $storage->switchTo('request');
        SentrySdk::startContext();
        SentrySdk::endContext();

        $this->assertNull($storage->get());

        SentrySdk::endContext();

        $this->assertNull($storage->get());
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
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
        SentrySdk::init($firstClient);

        $storage->switchTo('request');
        SentrySdk::startContext();
        $previousScope = SentrySdk::getIsolationScope();

        SentrySdk::init($secondClient);

        $this->assertNull($storage->get());
        $this->assertNotSame($previousScope, SentrySdk::getIsolationScope());

        SentrySdk::endContext();

        $this->assertNull($storage->get());

        SentrySdk::startContext();

        $this->assertNotNull($storage->get());
        $this->assertSame($secondClient, SentrySdk::getClient());

        SentrySdk::endContext();
    }

    public function testReplacingRuntimeContextStorageDiscardsContextFromPreviousStorage(): void
    {
        $firstStorage = new StubRuntimeContextStorage();
        $secondStorage = new StubRuntimeContextStorage();

        SentrySdk::setRuntimeContextStorage($firstStorage);
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext();

        $this->assertNotNull($firstStorage->get());

        SentrySdk::setRuntimeContextStorage($secondStorage);

        $this->assertNull($firstStorage->get());
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());

        SentrySdk::startContext();

        $this->assertNull($firstStorage->get());
        $this->assertSame(SentrySdk::getCurrentRuntimeContext(), $secondStorage->get());

        SentrySdk::endContext();
    }

    public function testUnregisteringRuntimeContextStorageRestoresProcessLocalContext(): void
    {
        $storage = new StubRuntimeContextStorage();

        SentrySdk::setRuntimeContextStorage($storage);
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();

        SentrySdk::startContext();

        $this->assertNotNull($storage->get());

        SentrySdk::setRuntimeContextStorage(null);

        $this->assertNull($storage->get());
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());

        SentrySdk::startContext();

        $this->assertNull($storage->get());
        $this->assertNotSame($globalScope, SentrySdk::getIsolationScope());

        SentrySdk::endContext();

        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testSettingRuntimeContextStorageKeepsGlobalFallbackContext(): void
    {
        SentrySdk::init();

        $globalContext = SentrySdk::getCurrentRuntimeContext();
        SentrySdk::getIsolationScope()->setTag('baseline', 'yes');

        SentrySdk::setRuntimeContextStorage(new StubRuntimeContextStorage());

        $this->assertSame($globalContext, SentrySdk::getCurrentRuntimeContext());
        $this->assertSame('yes', $this->getCurrentScopeTag('baseline'));
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

        SentrySdk::init($client);

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

        SentrySdk::init($client);

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

        SentrySdk::init($client);
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

    public function testWithContextReturnsCallbackResultAndRestoresGlobalIsolationScope(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();
        $callbackScope = null;

        $result = SentrySdk::withContext(static function () use (&$callbackScope): string {
            $callbackScope = SentrySdk::getIsolationScope();

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertNotNull($callbackScope);
        $this->assertNotSame($globalScope, $callbackScope);
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testNestedWithContextReusesOuterContext(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();
        $outerScope = null;
        $innerScope = null;
        $outerContextId = null;
        $innerContextId = null;

        SentrySdk::withContext(function () use (&$outerScope, &$innerScope, &$outerContextId, &$innerContextId, $globalScope): void {
            $outerScope = SentrySdk::getIsolationScope();
            $outerContextId = SentrySdk::getCurrentRuntimeContext()->getId();

            SentrySdk::getIsolationScope()->setTag('outer', 'yes');

            SentrySdk::withContext(static function () use (&$innerScope, &$innerContextId): void {
                $innerScope = SentrySdk::getIsolationScope();
                $innerContextId = SentrySdk::getCurrentRuntimeContext()->getId();
            });

            $event = Event::createEvent();

            $event = SentrySdk::getGlobalScope()->merge(SentrySdk::getIsolationScope())->applyToEvent($event);

            $this->assertNotSame($globalScope, SentrySdk::getIsolationScope());
            $this->assertSame('yes', $event->getTags()['outer'] ?? null);
            $this->assertSame($outerContextId, SentrySdk::getCurrentRuntimeContext()->getId());
        });

        $this->assertNotNull($outerScope);
        $this->assertNotNull($innerScope);
        $this->assertNotNull($outerContextId);
        $this->assertNotNull($innerContextId);
        $this->assertSame($outerScope, $innerScope);
        $this->assertSame($outerContextId, $innerContextId);
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    public function testWithContextEndsContextWhenCallbackThrows(): void
    {
        SentrySdk::init();

        $globalScope = SentrySdk::getIsolationScope();
        $callbackScope = null;

        try {
            SentrySdk::withContext(static function () use (&$callbackScope): void {
                $callbackScope = SentrySdk::getIsolationScope();

                throw new \RuntimeException('boom');
            });

            $this->fail('The callback exception should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertNotNull($callbackScope);
        $this->assertNotSame($globalScope, $callbackScope);
        $this->assertSame($globalScope, SentrySdk::getIsolationScope());
    }

    private function getCurrentScopeTraceparent(): string
    {
        $traceparent = '';

        $traceparent = SentrySdk::getIsolationScope()->getPropagationContext()->toTraceparent();

        return $traceparent;
    }

    private function getCurrentScopeTag(string $key): ?string
    {
        $event = SentrySdk::getGlobalScope()->merge(SentrySdk::getIsolationScope())->applyToEvent(Event::createEvent());

        return $event !== null ? $event->getTags()[$key] ?? null : null;
    }
}
