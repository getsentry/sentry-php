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
}
