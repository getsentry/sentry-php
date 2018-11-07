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
use Sentry\State\Hub;
use Sentry\State\Scope;

final class HubTest extends TestCase
{
    public function testConstructorCreatesScopeAutomatically(): void
    {
        $hub = new Hub(null, null);

        $this->assertNotNull($hub->getScope());
    }

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
        $hub = new Hub($this->createMock(ClientInterface::class), $scope);

        $this->assertSame($scope, $hub->getScope());
    }

    public function testGetStack(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope = new Scope();
        $hub = new Hub($client, $scope);

        $stack = $hub->getStack();

        $this->assertCount(1, $stack);
        $this->assertSame($client, $stack[0]->getClient());
        $this->assertSame($scope, $stack[0]->getScope());
    }

    public function testGetStackTop(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope = new Scope();
        $hub = new Hub($client, $scope);

        $stackTop = $hub->getStackTop();

        $this->assertSame($client, $stackTop->getClient());
        $this->assertSame($scope, $stackTop->getScope());

        $scope = $hub->pushScope();

        $stackTop = $hub->getStackTop();

        $this->assertSame($client, $stackTop->getClient());
        $this->assertSame($scope, $stackTop->getScope());
    }

    public function testGetLastEventId(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturn('92db40a886c0458288c7c83935a350ef');

        $hub = new Hub($client);

        $this->assertNull($hub->getLastEventId());
        $this->assertEquals($hub->captureMessage('foo'), $hub->getLastEventId());
    }

    public function testPushScope(): void
    {
        $hub = new Hub($this->createMock(ClientInterface::class));

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
        $hub = new Hub($this->createMock(ClientInterface::class));

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
        $hub = new Hub($this->createMock(ClientInterface::class), $scope);

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

        $hub = new Hub($client);
        $scope = $hub->pushScope();

        $hub->configureScope(function (Scope $scopeArg) use ($scope, &$callbackInvoked): void {
            $this->assertSame($scope, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
        $this->assertSame($scope, $hub->getScope());
    }

    public function testBindClient(): void
    {
        /** @var ClientInterface|MockObject $client1 */
        $client1 = $this->createMock(ClientInterface::class);

        /** @var ClientInterface|MockObject $client2 */
        $client2 = $this->createMock(ClientInterface::class);

        $hub = new Hub($client1);

        $this->assertSame($client1, $hub->getClient());

        $hub->bindClient($client2);

        $this->assertSame($client2, $hub->getClient());
    }

    public function testCaptureMessageDoesNothingIfClientIsNotBinded()
    {
        $hub = new Hub();

        $this->assertNull($hub->captureMessage('foo'));
    }

    public function testCaptureMessage(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureMessage('foo'));
    }

    public function testCaptureExceptionDoesNothingIfClientIsNotBinded()
    {
        $hub = new Hub();

        $this->assertNull($hub->captureException(new \RuntimeException()));
    }

    public function testCaptureException(): void
    {
        $exception = new \RuntimeException('foo');

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureException($exception));
    }

    public function testAddBreadcrumbDoesNothingIfClientIsNotBinded(): void
    {
        $scope = new Scope();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $hub = new Hub(null, $scope);
        $hub->addBreadcrumb($breadcrumb);

        $this->assertEmpty($scope->getBreadcrumbs());
    }

    public function testAddBreadcrumb(): void
    {
        $scope = new Scope();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('addBreadcrumb')
            ->with($breadcrumb, $scope);

        $hub = new Hub($client, $scope);
        $hub->addBreadcrumb($breadcrumb);
    }

    public function testCaptureEvent(): void
    {
        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(['message' => 'test'])
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureEvent(['message' => 'test']));
    }
}
