<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class SentrySdkTest extends TestCase
{
    /**
     * @group legacy
     *
     * @expectedDeprecation The Sentry\SentrySdk::getCurrentHub() method is deprecated since version 2.2 and will be removed in 3.0.
     */
    public function testInit(): void
    {
        $hub1 = SentrySdk::init();
        $hub2 = SentrySdk::getCurrentHub();

        $this->assertSame($hub1, $hub2);
        $this->assertNotSame(SentrySdk::init(), SentrySdk::init());
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation The Sentry\SentrySdk::getCurrentHub() method is deprecated since version 2.2 and will be removed in 3.0.
     */
    public function testGetCurrentHub(): void
    {
        SentrySdk::init();

        $hub2 = SentrySdk::getCurrentHub();
        $hub3 = SentrySdk::getCurrentHub();

        $this->assertSame($hub2, $hub3);
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation The Sentry\SentrySdk::setCurrentHub() method is deprecated since version 2.2 and will be removed in 3.0.
     * @expectedDeprecation The Sentry\SentrySdk::getCurrentHub() method is deprecated since version 2.2 and will be removed in 3.0.
     */
    public function testSetCurrentHub(): void
    {
        $hub = new Hub();

        $this->assertSame($hub, SentrySdk::setCurrentHub($hub));
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }

    public function testGetLastEventId(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturn('c3291ed00c34427bb987f1deabd956bd');

        $this->assertNull(SentrySdk::getLastEventId());

        SentrySdk::bindClient($client);
        SentrySdk::captureMessage('foo bar');

        $this->assertSame('c3291ed00c34427bb987f1deabd956bd', SentrySdk::getLastEventId());
    }

    public function testPushScope(): void
    {
        $scope = SentrySdk::pushScope();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with([], $scope);

        SentrySdk::bindClient($client);
        SentrySdk::captureEvent([]);
    }

    public function testPopScope(): void
    {
        $this->assertFalse(SentrySdk::popScope());

        $scope1 = SentrySdk::pushScope();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with([], $scope1);

        SentrySdk::bindClient($client);

        $scope2 = SentrySdk::pushScope();

        $this->assertNotSame($scope1, $scope2);
        $this->assertTrue(SentrySdk::popScope());

        SentrySdk::captureEvent([]);
    }

    public function testWithScope(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);

        SentrySdk::bindClient($client);
        SentrySdk::withScope(function (Scope $scopeArg) use ($client): void {
            $client->expects($this->once())
                ->method('captureEvent')
                ->with([], $scopeArg);

            SentrySdk::captureEvent([]);
        });
    }

    public function testConfigureScope(): void
    {
        $callbackCalled = false;
        $scope = null;

        SentrySdk::configureScope(static function (Scope $scopeArg) use (&$callbackCalled, &$scope): void {
            $callbackCalled = true;
            $scope = $scopeArg;
        });

        $this->assertTrue($callbackCalled);
        $this->assertNotNull($scope);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with([], $scope);

        SentrySdk::bindClient($client);
        SentrySdk::captureEvent([]);
    }

    public function testBindClient(): void
    {
        /** @var ClientInterface&MockObject $client1 */
        $client1 = $this->createMock(ClientInterface::class);

        $this->assertNull(SentrySdk::getClient());

        SentrySdk::bindClient($client1);

        $client2 = SentrySdk::getClient();

        $this->assertSame($client1, $client2);
    }

    public function testCaptureMessage(): void
    {
        $this->assertNull(SentrySdk::captureMessage('foo bar'));

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo bar')
            ->willReturn('51884e708f85482d8c98b16dc6c3efcf');

        SentrySdk::bindClient($client);

        $this->assertSame('51884e708f85482d8c98b16dc6c3efcf', SentrySdk::captureMessage('foo bar'));
    }

    public function testCaptureException(): void
    {
        $exception = new \Exception();

        $this->assertNull(SentrySdk::captureException($exception));

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn('8f19c2ecf1d1429594c4b8b7b04d4be9');

        SentrySdk::bindClient($client);

        $this->assertSame('8f19c2ecf1d1429594c4b8b7b04d4be9', SentrySdk::captureException($exception));
    }

    public function testCaptureEvent(): void
    {
        $this->assertNull(SentrySdk::captureEvent([]));

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(['foo' => 'bar'])
            ->willReturn('57f29461c03640228bc996970dbef8f1');

        SentrySdk::bindClient($client);

        $this->assertSame('57f29461c03640228bc996970dbef8f1', SentrySdk::captureEvent(['foo' => 'bar']));
    }

    public function testCaptureLastError(): void
    {
        $this->assertNull(SentrySdk::captureLastError());

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError')
            ->willReturn('902d6cfece4c49c7992bf6bfba1e9d2d');

        SentrySdk::bindClient($client);

        $this->assertSame('902d6cfece4c49c7992bf6bfba1e9d2d', SentrySdk::captureLastError());
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_DEBUG, Breadcrumb::TYPE_DEFAULT, 'user_error');

        $this->assertFalse(SentrySdk::addBreadcrumb($breadcrumb));

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        SentrySdk::bindClient($client);

        $this->assertTrue(SentrySdk::addBreadcrumb($breadcrumb));
    }

    public function testGetIntegration(): void
    {
        /** @var IntegrationInterface&MockObject $integration */
        $integration = $this->createMock(IntegrationInterface::class);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with('Foo\\Bar')
            ->willReturn($integration);

        SentrySdk::bindClient($client);

        $this->assertSame($integration, SentrySdk::getIntegration('Foo\\Bar'));
    }
}
