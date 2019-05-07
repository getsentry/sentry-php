<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Transport\AsyncTransportInterface;

final class HubTest extends TestCase
{
    public function testConstructorCreatesScopeAutomatically(): void
    {
        $hub = new Hub(null, null);

        $this->assertNotNull($this->getScope($hub));
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

        $this->assertSame($scope, $this->getScope($hub));
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

        $scope1 = $this->getScope($hub);
        $client1 = $hub->getClient();

        $scope2 = $hub->pushScope();
        $client2 = $hub->getClient();

        $this->assertNotSame($scope1, $scope2);
        $this->assertSame($scope2, $this->getScope($hub));
        $this->assertSame($client1, $client2);
        $this->assertSame($client1, $hub->getClient());
    }

    public function testPopScope(): void
    {
        $hub = new Hub($this->createMock(ClientInterface::class));

        $scope1 = $this->getScope($hub);
        $client = $hub->getClient();

        $scope2 = $hub->pushScope();

        $this->assertSame($scope2, $this->getScope($hub));
        $this->assertSame($client, $hub->getClient());

        $this->assertTrue($hub->popScope());

        $this->assertSame($scope1, $this->getScope($hub));
        $this->assertSame($client, $hub->getClient());

        $this->assertFalse($hub->popScope());

        $this->assertSame($scope1, $this->getScope($hub));
        $this->assertSame($client, $hub->getClient());
    }

    public function testWithScope(): void
    {
        $scope = new Scope();
        $hub = new Hub($this->createMock(ClientInterface::class), $scope);

        $this->assertSame($scope, $this->getScope($hub));

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
        $this->assertSame($scope, $this->getScope($hub));
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
        $this->assertSame($scope, $this->getScope($hub));
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
            ->with('foo', Severity::debug())
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureMessage('foo', Severity::debug()));
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
        $client = ClientBuilder::create()->getClient();
        $hub = new Hub($client);
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $hub->addBreadcrumb($breadcrumb);

        $this->assertSame([$breadcrumb], $this->getScope($hub)->getBreadcrumbs());
    }

    public function testAddBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsZero(): void
    {
        $client = ClientBuilder::create(['max_breadcrumbs' => 0])->getClient();
        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $this->assertEmpty($this->getScope($hub)->getBreadcrumbs());
    }

    public function testAddBreadcrumbRespectsMaxBreadcrumbsLimit(): void
    {
        $client = ClientBuilder::create(['max_breadcrumbs' => 2])->getClient();
        $hub = new Hub($client);
        $scope = $this->getScope($hub);

        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'foo');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'bar');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'baz');

        $hub->addBreadcrumb($breadcrumb1);
        $hub->addBreadcrumb($breadcrumb2);

        $this->assertSame([$breadcrumb1, $breadcrumb2], $scope->getBreadcrumbs());

        $hub->addBreadcrumb($breadcrumb3);

        $this->assertSame([$breadcrumb2, $breadcrumb3], $scope->getBreadcrumbs());
    }

    public function testAddBreadcrumbDoesNothingWhenBeforeBreadcrumbCallbackReturnsNull(): void
    {
        $callback = function (): ?Breadcrumb {
            return null;
        };
        $client = ClientBuilder::create(['before_breadcrumb' => $callback])->getClient();
        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));

        $this->assertEmpty($this->getScope($hub)->getBreadcrumbs());
    }

    public function testAddBreadcrumbStoresBreadcrumbReturnedByBeforeBreadcrumbCallback(): void
    {
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $callback = function () use ($breadcrumb2): ?Breadcrumb {
            return $breadcrumb2;
        };
        $client = ClientBuilder::create(['before_breadcrumb' => $callback])->getClient();
        $hub = new Hub($client);

        $hub->addBreadcrumb($breadcrumb1);

        $this->assertSame([$breadcrumb2], $this->getScope($hub)->getBreadcrumbs());
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

    private function getScope(HubInterface $hub): Scope
    {
        $method = new \ReflectionMethod($hub, 'getScope');
        $method->setAccessible(true);

        return $method->invoke($hub);
    }
}
