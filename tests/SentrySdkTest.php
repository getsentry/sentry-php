<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;

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

            SentrySdk::withContext(function () use (&$innerHub, &$innerContextId): void {
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
}
