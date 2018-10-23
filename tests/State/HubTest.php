<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class HubTest extends TestCase
{
    public function testGetClient(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $hub = new Hub($client);

        $this->assertSame($client, $hub->getClient());
    }

    public function testGetScope(): void
    {
        $scope = new Scope();
        $hub = new Hub(null, $scope);

        $this->assertSame($scope, $hub->getScope());
    }

    public function testGetStack(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope = new Scope();

        $hub = new Hub($client, $scope);
        $stack = $hub->getStack();

        $this->assertNotEmpty($stack);
        $this->assertSame($client, $stack[0]->getClient());
        $this->assertSame($scope, $stack[0]->getScope());
    }

    public function testGetStackTop(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope1 = new Scope();

        $hub = new Hub($client, $scope1);
        $layer = $hub->getStackTop();

        $this->assertSame($client, $layer->getClient());
        $this->assertSame($scope1, $layer->getScope());
    }

    public function testGetLastEventId(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', [], ['level' => null])
            ->willReturn('92db40a886c0458288c7c83935a350ef');

        $hub = new Hub();

        $this->assertNull($hub->getLastEventId());

        $hub->bindClient($client);

        $this->assertEquals($hub->captureMessage('foo'), $hub->getLastEventId());
    }

    public function testPushScope(): void
    {
        $hub = new Hub();

        $this->assertCount(1, $hub->getStack());

        $scope1 = $hub->getScope();
        $scope2 = $hub->pushScope();

        $layers = $hub->getStack();

        $this->assertCount(2, $layers);
        $this->assertNotSame($scope1, $scope2);
        $this->assertSame($scope1, $layers[0]->getScope());
        $this->assertSame($scope2, $layers[1]->getScope());
    }

    public function testPopScope(): void
    {
        $hub = new Hub();

        $this->assertCount(1, $hub->getStack());

        $scope1 = $hub->getScope();
        $scope2 = $hub->pushScope();

        $this->assertSame($scope2, $hub->getScope());

        $this->assertTrue($hub->popScope());
        $this->assertSame($scope1, $hub->getScope());

        $this->assertFalse($hub->popScope());
        $this->assertSame($scope1, $hub->getScope());
    }

    public function testWithScope(): void
    {
        $scope = new Scope();
        $hub = new Hub(null, $scope);

        $this->assertSame($scope, $hub->getScope());

        $callbackInvoked = false;

        try {
            $hub->withScope(function (Scope $scopeArg) use ($scope, &$callbackInvoked): void {
                $this->assertNotSame($scope, $scopeArg);

                $callbackInvoked = true;

                // We throw to test that the scope is correctly popped form the
                // stack regardless
                throw new \RuntimeException();
            });
        } catch (\RuntimeException $exception) {
            // Do nothing, we catch this exception to not make the test fail
        }

        $this->assertTrue($callbackInvoked);
        $this->assertSame($scope, $hub->getScope());
    }

    public function testConfigureScope(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope = new Scope();
        $hub = new Hub(null, $scope);

        $this->assertSame($scope, $hub->getScope());

        $callbackInvoked = false;

        $hub->configureScope(function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertFalse($callbackInvoked);

        $hub->bindClient($client);

        $hub->configureScope(function (Scope $scopeArg) use ($scope, &$callbackInvoked): void {
            $this->assertSame($scope, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
        $this->assertSame($scope, $hub->getScope());
    }

    public function testBindClient(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $hub = new Hub();

        $this->assertNull($hub->getClient());

        $hub->bindClient($client);

        $this->assertNotNull($hub->getClient());
        $this->assertSame($client, $hub->getClient());
    }

    public function testCaptureMessage(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $hub = new Hub();

        $this->assertNull($hub->captureMessage('foo'));

        $hub->bindClient($client);

        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', [], ['level' => Severity::debug()])
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureMessage('foo', Severity::debug()));
    }

    public function testCaptureException(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $hub = new Hub();

        $exception = new \RuntimeException('foo');

        $this->assertNull($hub->captureException($exception));

        $hub->bindClient($client);

        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureException($exception));
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $scope = new Scope();

        $hub = new Hub(null, $scope);
        $hub->addBreadcrumb($breadcrumb);

        $this->assertSame([$breadcrumb], $scope->getBreadcrumbs());
    }
}
