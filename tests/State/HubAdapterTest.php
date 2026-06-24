<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\CheckInStatus;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Integration\IntegrationInterface;
use Sentry\MonitorConfig;
use Sentry\MonitorSchedule;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubAdapter;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Sentry\Tracing\TransactionContext;
use Sentry\Util\SentryUid;

final class HubAdapterTest extends TestCase
{
    public function testGetInstance(): void
    {
        $this->assertSame(HubAdapter::getInstance(), HubAdapter::getInstance());
    }

    public function testGetInstanceReturnsUncloneableInstance(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cloning is forbidden.');

        clone HubAdapter::getInstance();
    }

    public function testHubAdapterThrowsExceptionOnSerialization(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Serializing instances of this class is forbidden.');

        serialize(HubAdapter::getInstance());
    }

    public function testHubAdapterThrowsExceptionOnUnserialization(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Unserializing instances of this class is forbidden.');

        unserialize('O:23:"Sentry\State\HubAdapter":0:{}');
    }

    public function testGetClient(): void
    {
        $client = $this->createMock(ClientInterface::class);

        HubAdapter::getInstance()->bindClient($client);

        $this->assertSame($client, HubAdapter::getInstance()->getClient());
    }

    public function testGetLastEventId(): void
    {
        $event = Event::createEvent();

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->willReturn($event->getId());

        SentrySdk::getGlobalScope()->setClient($client);

        HubAdapter::getInstance()->captureEvent($event);

        $this->assertSame($event->getId(), HubAdapter::getInstance()->getLastEventId());
    }

    public function testWithScope(): void
    {
        $baseScope = SentrySdk::getIsolationScope();

        $returnValue = HubAdapter::getInstance()->withScope(static function (Scope $scope): string {
            $scope->setTag('nested', 'yes');

            return 'foobarbaz';
        });

        $this->assertSame('foobarbaz', $returnValue);
        $this->assertSame($baseScope, SentrySdk::getIsolationScope());

        $event = $baseScope->applyToEvent(Event::createEvent());
        $this->assertNotNull($event);
        $this->assertArrayNotHasKey('nested', $event->getTags());
    }

    public function testConfigureScope(): void
    {
        HubAdapter::getInstance()->configureScope(static function (Scope $scope): void {
            $scope->setTag('foo', 'bar');
        });

        $event = SentrySdk::getIsolationScope()->applyToEvent(Event::createEvent());

        $this->assertNotNull($event);
        $this->assertSame(['foo' => 'bar'], $event->getTags());
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with($expectedFunctionCallArgs[0], $expectedFunctionCallArgs[1], $this->isInstanceOf(Scope::class), $expectedFunctionCallArgs[2] ?? null)
            ->willReturn($eventId);

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($eventId, HubAdapter::getInstance()->captureMessage(...$expectedFunctionCallArgs));
    }

    public static function captureMessageDataProvider(): \Generator
    {
        yield [['foo', Severity::debug()]];
        yield [['foo', Severity::debug(), new EventHint()]];
    }

    /**
     * @dataProvider captureExceptionDataProvider
     */
    public function testCaptureException(array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($expectedFunctionCallArgs[0], $this->isInstanceOf(Scope::class), $expectedFunctionCallArgs[1] ?? null)
            ->willReturn($eventId);

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($eventId, HubAdapter::getInstance()->captureException(...$expectedFunctionCallArgs));
    }

    public static function captureExceptionDataProvider(): \Generator
    {
        yield [[new \Exception('foo')]];
        yield [[new \Exception('foo'), new EventHint()]];
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray([]);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint, $this->isInstanceOf(Scope::class))
            ->willReturn($event->getId());

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($event->getId(), HubAdapter::getInstance()->captureEvent($event, $hint));
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError')
            ->with($this->isInstanceOf(Scope::class), $expectedFunctionCallArgs[0] ?? null)
            ->willReturn($eventId);

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($eventId, HubAdapter::getInstance()->captureLastError(...$expectedFunctionCallArgs));
    }

    public static function captureLastErrorDataProvider(): \Generator
    {
        yield [[]];
        yield [[new EventHint()]];
    }

    public function testCaptureCheckIn(): void
    {
        $checkInId = SentryUid::generate();

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'environment' => Event::DEFAULT_ENVIRONMENT,
                'release' => '1.1.8',
            ]));
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($checkInId): bool {
                $checkIn = $event->getCheckIn();

                return $checkIn !== null && $checkIn->getId() === $checkInId;
            }), null, $this->isInstanceOf(Scope::class));

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($checkInId, HubAdapter::getInstance()->captureCheckIn(
            'test-crontab',
            CheckInStatus::ok(),
            10,
            new MonitorConfig(
                MonitorSchedule::crontab('*/5 * * * *'),
                5,
                30,
                'UTC'
            ),
            $checkInId
        ));
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_DEBUG, Breadcrumb::TYPE_ERROR, 'user');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertTrue(HubAdapter::getInstance()->addBreadcrumb($breadcrumb));
    }

    public function testGetIntegration(): void
    {
        $integration = $this->createMock(IntegrationInterface::class);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getIntegration')
            ->with(\get_class($integration))
            ->willReturn($integration);

        SentrySdk::getGlobalScope()->setClient($client);

        $this->assertSame($integration, HubAdapter::getInstance()->getIntegration(\get_class($integration)));
    }

    public function testStartTransaction(): void
    {
        $transactionContext = new TransactionContext('test-transaction');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options());

        SentrySdk::getGlobalScope()->setClient($client);

        $transaction = HubAdapter::getInstance()->startTransaction($transactionContext);

        $this->assertSame('test-transaction', $transaction->getName());
    }

    public function testGetTransaction(): void
    {
        $transaction = HubAdapter::getInstance()->startTransaction(new TransactionContext());
        SentrySdk::getIsolationScope()->setSpan($transaction);

        $this->assertSame($transaction, HubAdapter::getInstance()->getTransaction());
    }

    public function testGetSpan(): void
    {
        $span = new Span();
        SentrySdk::getIsolationScope()->setSpan($span);

        $this->assertSame($span, HubAdapter::getInstance()->getSpan());
    }

    public function testSetSpan(): void
    {
        $span = new Span();

        $this->assertSame(HubAdapter::getInstance(), HubAdapter::getInstance()->setSpan($span));
        $this->assertSame($span, SentrySdk::getIsolationScope()->getSpan());
    }
}
