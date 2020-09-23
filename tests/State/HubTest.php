<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\TransactionContext;

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
        $eventId = EventId::generate();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->willReturn($eventId);

        $hub = new Hub($client);

        $this->assertNull($hub->getLastEventId());
        $this->assertSame($hub->captureMessage('foo'), $hub->getLastEventId());
        $this->assertSame($eventId, $hub->getLastEventId());
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
        $eventId = EventId::generate();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', Severity::debug())
            ->willReturn($eventId);

        $hub = new Hub();

        $this->assertNull($hub->captureMessage('foo'));

        $hub->bindClient($client);

        $this->assertSame($eventId, $hub->captureMessage('foo', Severity::debug()));
    }

    public function testCaptureException(): void
    {
        $eventId = EventId::generate();
        $exception = new \RuntimeException('foo');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn($eventId);

        $hub = new Hub();

        $this->assertNull($hub->captureException(new \RuntimeException()));

        $hub->bindClient($client);

        $this->assertSame($eventId, $hub->captureException($exception));
    }

    public function testCaptureLastError(): void
    {
        $eventId = EventId::generate();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError')
            ->willReturn($eventId);

        $hub = new Hub();

        $this->assertNull($hub->captureLastError());

        $hub->bindClient($client);

        $this->assertSame($eventId, $hub->captureLastError());
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent($eventId = EventId::generate());

        $event->setMessage('test');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event)
            ->willReturn($eventId);

        $hub = new Hub();

        $this->assertNull($hub->captureEvent(Event::createEvent()));

        $hub->bindClient($client);

        $this->assertSame($eventId, $hub->captureEvent($event));
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
            $event = $scope->applyToEvent(Event::createEvent(), []);

            $this->assertNotNull($event);
            $this->assertEmpty($event->getBreadcrumbs());
        });

        $hub->bindClient($client);
        $hub->addBreadcrumb($breadcrumb);
        $hub->configureScope(function (Scope $scope) use (&$callbackInvoked, $breadcrumb): void {
            $event = $scope->applyToEvent(Event::createEvent(), []);

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
            $event = $scope->applyToEvent(Event::createEvent(), []);

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
            $event = $scope->applyToEvent(Event::createEvent(), []);

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb1, $breadcrumb2], $event->getBreadcrumbs());
        });

        $hub->addBreadcrumb($breadcrumb3);

        $hub->configureScope(function (Scope $scope) use ($breadcrumb2, $breadcrumb3): void {
            $event = $scope->applyToEvent(Event::createEvent(), []);

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
            $event = $scope->applyToEvent(Event::createEvent(), []);

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
            $event = $scope->applyToEvent(Event::createEvent(), []);

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb2], $event->getBreadcrumbs());

            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
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

    /**
     * @dataProvider startTransactionDataProvider
     */
    public function testStartTransactionWithTracesSampler(Options $options, TransactionContext $transactionContext, bool $expectedSampled): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);

        $hub = new Hub($client);
        $transaction = $hub->startTransaction($transactionContext);

        $this->assertSame($expectedSampled, $transaction->getSampled());
    }

    public function startTransactionDataProvider(): iterable
    {
        yield [
            new Options([
                'traces_sampler' => static function (): float {
                    return 1;
                },
            ]),
            new TransactionContext(),
            true,
        ];

        yield [
            new Options([
                'traces_sampler' => static function (): float {
                    return 0;
                },
            ]),
            new TransactionContext(),
            false,
        ];

        yield [
            new Options([
                'traces_sampler' => static function (): int {
                    return 1;
                },
            ]),
            new TransactionContext(),
            true,
        ];

        yield [
            new Options([
                'traces_sampler' => static function (): int {
                    return 0;
                },
            ]),
            new TransactionContext(),
            false,
        ];

        yield [
            new Options([
                'traces_sample_rate' => 1.0,
            ]),
            new TransactionContext(),
            true,
        ];

        yield [
            new Options([
                'traces_sample_rate' => 0.0,
            ]),
            new TransactionContext(),
            false,
        ];

        yield [
            new Options([
                'traces_sample_rate' => 0.5,
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, true),
            true,
        ];

        yield [
            new Options([
                'traces_sample_rate' => 1.0,
            ]),
            new TransactionContext(TransactionContext::DEFAULT_NAME, false),
            false,
        ];
    }

    public function testStartTransactionDoesNothingIfTracingIsNotEnabled(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        $hub = new Hub($client);
        $transaction = $hub->startTransaction(new TransactionContext());

        $this->assertFalse($transaction->getSampled());
    }
}
