<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\NoOpClient;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\ScopeType;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;

final class SentrySdkTest extends TestCase
{
    public function testInitBindsClientToGlobalScope(): void
    {
        $client = $this->createMock(ClientInterface::class);

        SentrySdk::init($client);

        $this->assertSame($client, SentrySdk::getGlobalScope()->getClient());
    }

    public function testInitResetsIsolationAndCurrentScopes(): void
    {
        SentrySdk::init(new NoOpClient());

        $oldIsolationScope = SentrySdk::getIsolationScope();
        $oldCurrentScope = SentrySdk::getCurrentScope();

        SentrySdk::init(new NoOpClient());

        $this->assertNotSame($oldIsolationScope, SentrySdk::getIsolationScope());
        $this->assertNotSame($oldCurrentScope, SentrySdk::getCurrentScope());
    }

    public function testInitDoesNotResetGlobalScope(): void
    {
        SentrySdk::init(new NoOpClient());
        SentrySdk::getGlobalScope()->setTag('global', 'foo');

        SentrySdk::init(new NoOpClient());

        $event = Event::createEvent();
        $event = SentrySdk::getMergedScope()->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertSame('foo', $event->getTags()['global'] ?? null);
    }

    public function testScopesHaveCorrectTypes(): void
    {
        SentrySdk::init();

        $this->assertSame(ScopeType::global(), SentrySdk::getGlobalScope()->getType());
        $this->assertSame(ScopeType::isolation(), SentrySdk::getIsolationScope()->getType());
        $this->assertSame(ScopeType::current(), SentrySdk::getCurrentScope()->getType());
    }

    public function testScopesNoOpClientByDefault(): void
    {
        SentrySdk::init(new NoOpClient());

        $this->assertInstanceOf(NoOpClient::class, SentrySdk::getGlobalScope()->getClient());
        $this->assertInstanceOf(NoOpClient::class, SentrySdk::getIsolationScope()->getClient());
        $this->assertInstanceOf(NoOpClient::class, SentrySdk::getCurrentScope()->getClient());
    }

    public function testGetClientPrefersCurrentThenIsolationThenGlobal(): void
    {
        $globalClient = $this->createMock(ClientInterface::class);
        $isolationClient = $this->createMock(ClientInterface::class);
        $currentClient = $this->createMock(ClientInterface::class);

        SentrySdk::init(new NoOpClient());
        SentrySdk::getGlobalScope()->bindClient($globalClient);
        SentrySdk::getIsolationScope()->bindClient($isolationClient);
        SentrySdk::getCurrentScope()->bindClient($currentClient);

        $this->assertSame($currentClient, SentrySdk::getClient());

        SentrySdk::getCurrentScope()->bindClient(new NoOpClient());
        $this->assertSame($isolationClient, SentrySdk::getClient());

        SentrySdk::getIsolationScope()->bindClient(new NoOpClient());
        $this->assertSame($globalClient, SentrySdk::getClient());
    }

    public function testGetMergedScopeCombinesScopeData(): void
    {
        SentrySdk::init(new NoOpClient());

        SentrySdk::getGlobalScope()->setTag('global', 'yes');
        SentrySdk::getGlobalScope()->setTag('shared', 'global');
        SentrySdk::getIsolationScope()->setTag('isolation', 'yes');
        SentrySdk::getIsolationScope()->setTag('shared', 'isolation');
        SentrySdk::getCurrentScope()->setTag('current', 'yes');
        SentrySdk::getCurrentScope()->setTag('shared', 'current');

        $event = Event::createEvent();
        $event = SentrySdk::getMergedScope()->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertEquals([
            'global' => 'yes',
            'isolation' => 'yes',
            'current' => 'yes',
            'shared' => 'current',
        ], $event->getTags());
    }

    public function testStartAndEndContextIsolateScopeData(): void
    {
        SentrySdk::init();

        SentrySdk::configureScope(static function (\Sentry\State\Scope $scope): void {
            $scope->setTag('baseline', 'yes');
        });

        SentrySdk::startContext();

        SentrySdk::configureScope(static function (\Sentry\State\Scope $scope): void {
            $scope->setTag('request', 'yes');
        });

        SentrySdk::endContext();

        $event = Event::createEvent();
        $event = SentrySdk::getMergedScope()->applyToEvent($event);

        $this->assertNotNull($event);
        $this->assertArrayHasKey('baseline', $event->getTags());
        $this->assertArrayNotHasKey('request', $event->getTags());
    }

    public function testStartContextDoesNotInheritBaselineSpan(): void
    {
        SentrySdk::init();

        $baselineSpan = new Span(new SpanContext());
        SentrySdk::getCurrentScope()->setSpan($baselineSpan);

        SentrySdk::startContext();

        $this->assertNull(SentrySdk::getCurrentScope()->getSpan());

        SentrySdk::endContext();

        $this->assertSame($baselineSpan, SentrySdk::getCurrentScope()->getSpan());
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
            $transaction = SentrySdk::startTransaction(new TransactionContext('request-1'));
            SentrySdk::getCurrentScope()->setSpan($transaction);

            $this->assertSame($transaction, SentrySdk::getCurrentScope()->getSpan());
            $this->assertSame($transaction, SentrySdk::getCurrentScope()->getTransaction());
        });

        SentrySdk::withContext(function (): void {
            $this->assertNull(SentrySdk::getCurrentScope()->getSpan());
            $this->assertNull(SentrySdk::getCurrentScope()->getTransaction());
        });
    }

    public function testNestedStartContextIsNoOp(): void
    {
        SentrySdk::init();

        $globalContextId = SentrySdk::getCurrentRuntimeContext()->getId();

        SentrySdk::startContext();
        $firstContextId = SentrySdk::getCurrentRuntimeContext()->getId();

        SentrySdk::startContext();
        $secondContextId = SentrySdk::getCurrentRuntimeContext()->getId();

        $this->assertNotSame($globalContextId, $firstContextId);
        $this->assertSame($firstContextId, $secondContextId);

        SentrySdk::endContext();
        $this->assertSame($globalContextId, SentrySdk::getCurrentRuntimeContext()->getId());

        SentrySdk::endContext();
        $this->assertSame($globalContextId, SentrySdk::getCurrentRuntimeContext()->getId());
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

    public function testWithContextReturnsCallbackResultAndRestoresGlobalContext(): void
    {
        SentrySdk::init();

        $globalContextId = SentrySdk::getCurrentRuntimeContext()->getId();
        $globalIsolationScope = SentrySdk::getIsolationScope();
        $callbackContextId = null;
        $callbackIsolationScope = null;

        $result = SentrySdk::withContext(static function () use (&$callbackContextId, &$callbackIsolationScope): string {
            $callbackContextId = SentrySdk::getCurrentRuntimeContext()->getId();
            $callbackIsolationScope = SentrySdk::getIsolationScope();

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertNotNull($callbackContextId);
        $this->assertNotNull($callbackIsolationScope);
        $this->assertNotSame($globalContextId, $callbackContextId);
        $this->assertNotSame($globalIsolationScope, $callbackIsolationScope);
        $this->assertSame($globalContextId, SentrySdk::getCurrentRuntimeContext()->getId());
        $this->assertSame($globalIsolationScope, SentrySdk::getIsolationScope());
    }

    public function testNestedWithContextReusesOuterContext(): void
    {
        SentrySdk::init();

        $globalContextId = SentrySdk::getCurrentRuntimeContext()->getId();
        $outerContextId = null;
        $innerContextId = null;

        SentrySdk::withContext(function () use (&$outerContextId, &$innerContextId, $globalContextId): void {
            $outerContextId = SentrySdk::getCurrentRuntimeContext()->getId();

            SentrySdk::configureScope(static function (\Sentry\State\Scope $scope): void {
                $scope->setTag('outer', 'yes');
            });

            SentrySdk::withContext(static function () use (&$innerContextId): void {
                $innerContextId = SentrySdk::getCurrentRuntimeContext()->getId();
            });

            $event = Event::createEvent();
            $event = SentrySdk::getMergedScope()->applyToEvent($event);

            $this->assertNotNull($event);
            $this->assertNotSame($globalContextId, SentrySdk::getCurrentRuntimeContext()->getId());
            $this->assertSame('yes', $event->getTags()['outer'] ?? null);
            $this->assertSame($outerContextId, SentrySdk::getCurrentRuntimeContext()->getId());
        });

        $this->assertNotNull($outerContextId);
        $this->assertNotNull($innerContextId);
        $this->assertSame($outerContextId, $innerContextId);
        $this->assertSame($globalContextId, SentrySdk::getCurrentRuntimeContext()->getId());
    }

    public function testWithContextEndsContextWhenCallbackThrows(): void
    {
        SentrySdk::init();

        $globalContextId = SentrySdk::getCurrentRuntimeContext()->getId();
        $callbackContextId = null;

        try {
            SentrySdk::withContext(static function () use (&$callbackContextId): void {
                $callbackContextId = SentrySdk::getCurrentRuntimeContext()->getId();

                throw new \RuntimeException('boom');
            });

            $this->fail('The callback exception should be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertNotNull($callbackContextId);
        $this->assertNotSame($globalContextId, $callbackContextId);
        $this->assertSame($globalContextId, SentrySdk::getCurrentRuntimeContext()->getId());
    }

    private function getCurrentScopeTraceparent(): string
    {
        return SentrySdk::getIsolationScope()->getPropagationContext()->toTraceparent();
    }
}
