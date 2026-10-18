<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\Options;
use Sentry\Profiling\ContinuousProfiler;
use Sentry\Profiling\Profiles;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
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

    public function testStartContextUsesProvidedHubAsIs(): void
    {
        SentrySdk::init();

        $globalHub = SentrySdk::getCurrentHub();
        $span = new Span(new SpanContext());
        $hub = new Hub();
        $hub->setSpan($span);
        $traceparent = '';
        $hub->configureScope(static function (Scope $scope) use (&$traceparent): void {
            $traceparent = $scope->getPropagationContext()->toTraceparent();
        });

        SentrySdk::startContext($hub);

        $this->assertSame($hub, SentrySdk::getCurrentHub());
        $this->assertSame($span, SentrySdk::getCurrentHub()->getSpan());
        $this->assertSame($traceparent, $this->getCurrentScopeTraceparent());

        SentrySdk::endContext();

        $this->assertSame($globalHub, SentrySdk::getCurrentHub());
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
            $transaction = SentrySdk::getCurrentHub()->startTransaction(new TransactionContext('request-1'));
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

    public function testNestedStartContextIgnoresProvidedHub(): void
    {
        SentrySdk::init();

        $globalHub = SentrySdk::getCurrentHub();

        SentrySdk::startContext();
        $contextHub = SentrySdk::getCurrentHub();

        SentrySdk::startContext(new Hub());

        $this->assertSame($contextHub, SentrySdk::getCurrentHub());

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

    /**
     * @requires extension excimer
     */
    public function testFlushSendsCollectedProfileSamplesAndKeepsProfiling(): void
    {
        StubTransport::$events = [];

        SentrySdk::init()->bindClient(new Client(new Options(['profile_session_sample_rate' => 1.0]), StubTransport::getInstance()));

        $profiler = Profiles::getInstance()->continuousProfiler();
        $profiler->start();

        $profilerId = $profiler->getProfilerId();

        self::busyWait(0.05);

        SentrySdk::flush();

        $this->assertTrue($profiler->isRunning());
        $this->assertSame($profilerId, $profiler->getProfilerId());
        $this->assertCount(1, StubTransport::$events);

        $profileChunk = StubTransport::$events[0]->getProfileChunk();

        $this->assertNotNull($profileChunk);
        $this->assertSame($profilerId, $profileChunk->getProfilerId());

        self::busyWait(0.05);

        $profiler->stop();

        $this->assertCount(2, StubTransport::$events);
    }

    /**
     * @requires extension excimer
     */
    public function testEndContextStopsProfilerForUnfinishedRootSpans(): void
    {
        StubTransport::$events = [];

        self::initWithProfileLifecycleTrace();

        $profiler = Profiles::getInstance()->continuousProfiler();

        SentrySdk::startContext();
        SentrySdk::getCurrentHub()->startTransaction(TransactionContext::make());

        $this->assertTrue($profiler->isRunning());

        self::busyWait(0.05);

        SentrySdk::endContext();

        $this->assertFalse($profiler->isRunning());
        $this->assertCount(1, StubTransport::$events);
        $this->assertNotNull(StubTransport::$events[0]->getProfileChunk());
    }

    /**
     * @requires extension excimer
     */
    public function testEndContextKeepsProfilerRunningForRootSpansOfOtherContexts(): void
    {
        self::initWithProfileLifecycleTrace();

        $profiler = Profiles::getInstance()->continuousProfiler();
        $transaction = SentrySdk::getCurrentHub()->startTransaction(TransactionContext::make());

        SentrySdk::startContext();
        SentrySdk::getCurrentHub()->startTransaction(TransactionContext::make());
        SentrySdk::endContext();

        $this->assertTrue($profiler->isRunning());

        $transaction->finish();

        $this->assertFalse($profiler->isRunning());
    }

    /**
     * @requires extension excimer
     */
    public function testEndContextKeepsProfilerRunningWhileAnotherExecutionSharesTheContext(): void
    {
        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);

        self::initWithProfileLifecycleTrace();

        $profiler = Profiles::getInstance()->continuousProfiler();

        SentrySdk::startContext();
        SentrySdk::getCurrentHub()->startTransaction(TransactionContext::make());

        // A child coroutine of the request shares its context and ends first
        $storage->share('child');
        $storage->switchTo('child');

        SentrySdk::endContext();

        $this->assertTrue($profiler->isRunning());

        $storage->switchTo('default');

        SentrySdk::endContext();

        $this->assertFalse($profiler->isRunning());
    }

    /**
     * @requires extension excimer
     */
    public function testWithContextKeepsProfilerRunningWhileAnotherExecutionSharesTheContext(): void
    {
        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);

        self::initWithProfileLifecycleTrace();

        $profiler = Profiles::getInstance()->continuousProfiler();

        SentrySdk::withContext(static function () use ($storage): void {
            SentrySdk::getCurrentHub()->startTransaction(TransactionContext::make());

            // A detached child coroutine shares the context and outlives the callback
            $storage->share('child');
        });

        $this->assertTrue($profiler->isRunning());

        $storage->switchTo('child');

        SentrySdk::endContext();

        $this->assertFalse($profiler->isRunning());
    }

    /**
     * @requires extension excimer
     */
    public function testEndContextCapturesTheProfileChunksCompletedWhileSampling(): void
    {
        StubTransport::$events = [];

        SentrySdk::init()->bindClient(new Client(new Options(['profile_session_sample_rate' => 1.0]), StubTransport::getInstance()));

        $profiler = new ContinuousProfiler(5);
        $previousProfiler = self::replaceContinuousProfiler($profiler);

        try {
            $profiler->start();

            SentrySdk::startContext();

            self::busyWait(0.2);

            $this->assertSame([], StubTransport::$events);

            SentrySdk::endContext();

            $this->assertTrue($profiler->isRunning());
            $this->assertGreaterThanOrEqual(2, \count(StubTransport::$events));
        } finally {
            $profiler->stop();

            self::replaceContinuousProfiler($previousProfiler);
        }
    }

    /**
     * @requires extension excimer
     */
    public function testEndContextMarksTheProfilerIdleOnceNoOtherContextIsActive(): void
    {
        StubTransport::$events = [];

        $storage = new StubRuntimeContextStorage();
        SentrySdk::setRuntimeContextStorage($storage);
        SentrySdk::init()->bindClient(new Client(new Options(['profile_session_sample_rate' => 1.0]), StubTransport::getInstance()));

        $profiler = new ContinuousProfiler();
        $previousProfiler = self::replaceContinuousProfiler($profiler);

        try {
            $profiler->start();

            $storage->switchTo('first');
            SentrySdk::startContext();

            $storage->switchTo('second');
            SentrySdk::startContext();

            $storage->switchTo('first');
            SentrySdk::endContext();

            // The second context is still active, e.g. waiting for a database query
            usleep(200000);
            self::busyWait(0.05);

            $idleTimestamp = microtime(true);

            $storage->switchTo('second');
            SentrySdk::endContext();

            // No code runs until the next context starts
            usleep(200000);
            self::busyWait(0.05);
        } finally {
            $profiler->stop();

            self::replaceContinuousProfiler($previousProfiler);
        }

        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];
        $profileChunk = $event->getProfileChunk();

        $this->assertNotNull($profileChunk);

        $payload = $profileChunk->getFormattedData($event);

        $this->assertNotNull($payload);

        $idleSamples = array_values(array_filter($payload['profile']['samples'], static function (array $sample) use ($payload): bool {
            return $payload['profile']['stacks'][$sample['stack_id']] === [];
        }));

        $this->assertCount(1, $idleSamples);
        $this->assertGreaterThanOrEqual($idleTimestamp, $idleSamples[0]['timestamp']);
    }

    /**
     * @requires extension excimer
     */
    public function testStartContextEndsTheIdleTimeOfTheProfiler(): void
    {
        StubTransport::$events = [];

        SentrySdk::init()->bindClient(new Client(new Options(['profile_session_sample_rate' => 1.0]), StubTransport::getInstance()));

        $profiler = new ContinuousProfiler();
        $previousProfiler = self::replaceContinuousProfiler($profiler);

        try {
            $profiler->start();

            SentrySdk::startContext();
            self::busyWait(0.05);
            SentrySdk::endContext();

            // A health check that runs without a runtime context while the worker is idle
            usleep(200000);
            self::busyWait(0.02);
            usleep(200000);
            self::busyWait(0.02);

            SentrySdk::startContext();

            // A wait while handling the request, e.g. for a database query
            usleep(200000);
            self::busyWait(0.05);
        } finally {
            $profiler->stop();

            self::replaceContinuousProfiler($previousProfiler);
        }

        $this->assertCount(1, StubTransport::$events);

        $event = StubTransport::$events[0];
        $profileChunk = $event->getProfileChunk();

        $this->assertNotNull($profileChunk);

        $payload = $profileChunk->getFormattedData($event);

        $this->assertNotNull($payload);

        $idleSamples = array_filter($payload['profile']['samples'], static function (array $sample) use ($payload): bool {
            return $payload['profile']['stacks'][$sample['stack_id']] === [];
        });

        // The waits before and after the health check are idle, the wait while handling the request is not
        $this->assertCount(2, $idleSamples);
    }

    /**
     * @requires extension excimer
     */
    public function testEndContextCapturesTheProfileChunksAfterMarkingTheProfilerIdle(): void
    {
        StubTransport::$events = [];

        SentrySdk::init()->bindClient(new Client(new Options(['profile_session_sample_rate' => 1.0]), StubTransport::getInstance()));

        $profiler = new ContinuousProfiler(6060, 65.0, 0.05);
        $previousProfiler = self::replaceContinuousProfiler($profiler);

        try {
            $profiler->start();

            SentrySdk::startContext();

            self::busyWait(0.1);

            SentrySdk::endContext();

            $this->assertCount(1, StubTransport::$events);
        } finally {
            $profiler->stop();

            self::replaceContinuousProfiler($previousProfiler);
        }

        $event = StubTransport::$events[0];
        $profileChunk = $event->getProfileChunk();

        $this->assertNotNull($profileChunk);

        $payload = $profileChunk->getFormattedData($event);

        $this->assertNotNull($payload);

        // The chunk ends with the idle time, as the next sample can be too far away to share a chunk with it
        $lastSample = end($payload['profile']['samples']);

        $this->assertNotFalse($lastSample);
        $this->assertSame([], $payload['profile']['stacks'][$lastSample['stack_id']]);
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

    private static function initWithProfileLifecycleTrace(): void
    {
        SentrySdk::init()->bindClient(new Client(new Options([
            'traces_sample_rate' => 1.0,
            'profile_lifecycle' => 'trace',
            'profile_session_sample_rate' => 1.0,
        ]), StubTransport::getInstance()));
    }

    /**
     * Replaces the continuous profiler of the SDK, so that a test can complete chunks quickly, and
     * returns the previous one.
     */
    private static function replaceContinuousProfiler(ContinuousProfiler $continuousProfiler): ContinuousProfiler
    {
        $profiles = Profiles::getInstance();
        $previousContinuousProfiler = $profiles->continuousProfiler();

        $reflectionProperty = new \ReflectionProperty(Profiles::class, 'continuousProfiler');
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(true);
        }
        $reflectionProperty->setValue($profiles, $continuousProfiler);
        if (\PHP_VERSION_ID < 80100) {
            $reflectionProperty->setAccessible(false);
        }

        return $previousContinuousProfiler;
    }

    private static function busyWait(float $seconds): void
    {
        $end = microtime(true) + $seconds;

        while (microtime(true) < $end) {
            // Burn CPU so that Excimer collects samples
        }
    }
}
