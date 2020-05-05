<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class HubTest extends TestCase
{
    public function testGetClient(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $hub = new Hub($client);

        $this->assertSame($client, $hub->getClient());
    }

    public function testGetScope(): void
    {
        $callbackInvoked = false;
        $scope = new Scope();
        $hub = new Hub($this->createMock(ClientInterface::class), $scope);

        $hub->configureScope(function (Scope $scopeArg) use (&$callbackInvoked, $scope) {
            $this->assertSame($scope, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testGetLastEventId(): void
    {
        /** @var ClientInterface&MockObject $client */
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
        /** @var ClientInterface&MockObject $client1 */
        $client1 = $this->createMock(ClientInterface::class);
        $scope1 = new Scope();
        $hub = new Hub($client1, $scope1);

        $this->assertSame($client1, $hub->getClient());

        $scope2 = $hub->pushScope();

        $this->assertSame($client1, $hub->getClient());
        $this->assertNotSame($scope1, $scope2);

        $hub->configureScope(function (Scope $scopeArg) use (&$callbackInvoked, $scope2): void {
            $this->assertSame($scope2, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testPopScope(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $scope1 = new Scope();
        $hub = new Hub($client, $scope1);

        $this->assertFalse($hub->popScope());

        $scope2 = $hub->pushScope();

        $callbackInvoked = false;

        $hub->configureScope(function (Scope $scopeArg) use ($scope2, &$callbackInvoked): void {
            $this->assertSame($scope2, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
        $this->assertSame($client, $hub->getClient());

        $this->assertTrue($hub->popScope());

        $callbackInvoked = false;

        $hub->configureScope(function (Scope $scopeArg) use ($scope1, &$callbackInvoked): void {
            $this->assertSame($scope1, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
        $this->assertSame($client, $hub->getClient());

        $this->assertFalse($hub->popScope());

        $callbackInvoked = false;

        $hub->configureScope(function (Scope $scopeArg) use ($scope1, &$callbackInvoked): void {
            $this->assertSame($scope1, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
        $this->assertSame($client, $hub->getClient());
    }

    public function testWithScope(): void
    {
        $scope = new Scope();
        $hub = new Hub($this->createMock(ClientInterface::class), $scope);
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

        $callbackInvoked = false;

        $hub->configureScope(function (Scope $scopeArg) use (&$callbackInvoked, $scope): void {
            $this->assertSame($scope, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testConfigureScope(): void
    {
        $scope = new Scope();
        $hub = new Hub(null, $scope);

        $hub->configureScope(function (Scope $scopeArg) use ($scope, &$callbackInvoked): void {
            $this->assertSame($scope, $scopeArg);

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testBindClient(): void
    {
        /** @var ClientInterface&MockObject $client1 */
        $client1 = $this->createMock(ClientInterface::class);

        /** @var ClientInterface&MockObject $client2 */
        $client2 = $this->createMock(ClientInterface::class);

        $hub = new Hub($client1);

        $this->assertSame($client1, $hub->getClient());

        $hub->bindClient($client2);

        $this->assertSame($client2, $hub->getClient());
    }

    public function testCaptureMessage(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', Severity::debug())
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub();

        $this->assertNull($hub->captureMessage('foo'));

        $hub->bindClient($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureMessage('foo', Severity::debug()));
    }

    public function testCaptureException(): void
    {
        $exception = new \RuntimeException('foo');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub();

        $this->assertNull($hub->captureException(new \RuntimeException()));

        $hub->bindClient($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureException($exception));
    }

    public function testCaptureLastError(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError')
            ->willReturn('7565e130d1d14e639442110a6dd1cbab');

        $hub = new Hub();

        $this->assertNull($hub->captureLastError());

        $hub->bindClient($client);

        $this->assertEquals('7565e130d1d14e639442110a6dd1cbab', $hub->captureLastError());
    }

    public function testAddBreadcrumb(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        $callbackInvoked = false;
        $hub = new Hub();
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $hub->addBreadcrumb($breadcrumb);
        $hub->configureScope(function (Scope $scope): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertEmpty($event->getBreadcrumbs());
        });

        $hub->bindClient($client);
        $hub->addBreadcrumb($breadcrumb);
        $hub->configureScope(function (Scope $scope) use (&$callbackInvoked, $breadcrumb): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb], $event->getBreadcrumbs());

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testAddBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsZero(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['max_breadcrumbs' => 0]));

        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $hub->configureScope(function (Scope $scope): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertEmpty($event->getBreadcrumbs());
        });
    }

    public function testAddBreadcrumbRespectsMaxBreadcrumbsLimit(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
            ->method('getOptions')
            ->willReturn(new Options(['max_breadcrumbs' => 2]));

        $hub = new Hub($client);
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'foo');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'bar');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'baz');

        $hub->addBreadcrumb($breadcrumb1);
        $hub->addBreadcrumb($breadcrumb2);

        $hub->configureScope(function (Scope $scope) use ($breadcrumb1, $breadcrumb2): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb1, $breadcrumb2], $event->getBreadcrumbs());
        });

        $hub->addBreadcrumb($breadcrumb3);

        $hub->configureScope(function (Scope $scope) use ($breadcrumb2, $breadcrumb3): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb2, $breadcrumb3], $event->getBreadcrumbs());
        });
    }

    public function testAddBreadcrumbDoesNothingWhenBeforeBreadcrumbCallbackReturnsNull(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'before_breadcrumb' => static function () {
                    return null;
                },
            ]));

        $hub = new Hub($client);

        $hub->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'));
        $hub->configureScope(function (Scope $scope): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertEmpty($event->getBreadcrumbs());
        });
    }

    public function testAddBreadcrumbStoresBreadcrumbReturnedByBeforeBreadcrumbCallback(): void
    {
        $callbackInvoked = false;
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'before_breadcrumb' => static function () use ($breadcrumb2): Breadcrumb {
                    return $breadcrumb2;
                },
            ]));

        $hub = new Hub($client);

        $hub->addBreadcrumb($breadcrumb1);
        $hub->configureScope(function (Scope $scope) use (&$callbackInvoked, $breadcrumb2): void {
            $event = $scope->applyToEvent(new Event(), []);

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb2], $event->getBreadcrumbs());

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testCaptureEvent(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(['message' => 'test'])
            ->willReturn('2b867534eead412cbdb882fd5d441690');

        $hub = new Hub();

        $this->assertNull($hub->captureEvent([]));

        $hub->bindClient($client);

        $this->assertEquals('2b867534eead412cbdb882fd5d441690', $hub->captureEvent(['message' => 'test']));
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

        $hub = new Hub();

        $this->assertNull($hub->getIntegration('Foo\\Bar'));

        $hub->bindClient($client);

        $this->assertSame($integration, $hub->getIntegration('Foo\\Bar'));
    }
}
