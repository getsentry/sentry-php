<?php

declare(strict_types=1);

namespace Sentry\Tests\State;

use PHPUnit\Framework\MockObject\MockObject;
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
use Sentry\State\Hub;
use Sentry\State\HubAdapter;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
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

        SentrySdk::getCurrentHub()->bindClient($client);

        $this->assertSame($client, HubAdapter::getInstance()->getClient());
    }

    public function testGetLastEventId(): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('getLastEventId')
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, HubAdapter::getInstance()->getLastEventId());
    }

    public function testPushScope(): void
    {
        $scope = new Scope();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('pushScope')
            ->willReturn($scope);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($scope, HubAdapter::getInstance()->pushScope());
    }

    public function testPopScope(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('popScope')
            ->willReturn(true);

        SentrySdk::setCurrentHub($hub);

        $this->assertTrue(HubAdapter::getInstance()->popScope());
    }

    public function testWithScope(): void
    {
        $callback = static function (): string {
            return 'foobarbaz';
        };

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('withScope')
            ->with($callback)
            ->willReturnCallback($callback);

        SentrySdk::setCurrentHub($hub);

        $returnValue = HubAdapter::getInstance()->withScope($callback);

        $this->assertSame('foobarbaz', $returnValue);
    }

    public function testConfigureScope(): void
    {
        $callback = static function () {};

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('configureScope')
            ->with($callback);

        SentrySdk::setCurrentHub($hub);
        HubAdapter::getInstance()->configureScope($callback);
    }

    public function testBindClient(): void
    {
        $client = $this->createMock(ClientInterface::class);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('bindClient')
            ->with($client);

        SentrySdk::setCurrentHub($hub);
        HubAdapter::getInstance()->bindClient($client);
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, HubAdapter::getInstance()->captureMessage(...$expectedFunctionCallArgs));
    }

    public static function captureMessageDataProvider(): \Generator
    {
        yield [
            [
                'foo',
                Severity::debug(),
            ],
        ];

        yield [
            [
                'foo',
                Severity::debug(),
                new EventHint(),
            ],
        ];
    }

    /**
     * @dataProvider captureExceptionDataProvider
     */
    public function testCaptureException(array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();
        $exception = new \Exception();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureException')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, HubAdapter::getInstance()->captureException(...$expectedFunctionCallArgs));
    }

    public static function captureExceptionDataProvider(): \Generator
    {
        yield [
            [
                new \Exception('foo'),
            ],
        ];

        yield [
            [
                new \Exception('foo'),
                new EventHint(),
            ],
        ];
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent();
        $hint = EventHint::fromArray([]);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint)
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), HubAdapter::getInstance()->captureEvent($event, $hint));
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureLastError')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, HubAdapter::getInstance()->captureLastError(...$expectedFunctionCallArgs));
    }

    public static function captureLastErrorDataProvider(): \Generator
    {
        yield [
            [],
        ];

        yield [
            [
                new EventHint(),
            ],
        ];
    }

    public function testCaptureCheckIn()
    {
        $hub = new Hub();

        $options = new Options([
            'environment' => Event::DEFAULT_ENVIRONMENT,
            'release' => '1.1.8',
        ]);
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn($options);

        $hub->bindClient($client);
        SentrySdk::setCurrentHub($hub);

        $checkInId = SentryUid::generate();

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

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('addBreadcrumb')
            ->willReturn(true);

        SentrySdk::setCurrentHub($hub);

        $this->assertTrue(HubAdapter::getInstance()->addBreadcrumb($breadcrumb));
    }

    public function testGetIntegration(): void
    {
        $integration = $this->createMock(IntegrationInterface::class);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('getIntegration')
            ->with(\get_class($integration))
            ->willReturn($integration);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($integration, HubAdapter::getInstance()->getIntegration(\get_class($integration)));
    }

    public function testStartTransaction(): void
    {
        $transactionContext = new TransactionContext();
        $transaction = new Transaction($transactionContext);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('startTransaction')
            ->with($transactionContext)
            ->willReturn($transaction);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($transaction, HubAdapter::getInstance()->startTransaction($transactionContext));
    }

    public function testGetTransaction(): void
    {
        $transaction = new Transaction(new TransactionContext());

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('getTransaction')
            ->willReturn($transaction);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($transaction, HubAdapter::getInstance()->getTransaction());
    }

    public function testGetSpan(): void
    {
        $span = new Span();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('getSpan')
            ->willReturn($span);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($span, HubAdapter::getInstance()->getSpan());
    }

    public function testSetSpan(): void
    {
        $span = new Span();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('setSpan')
            ->with($span)
            ->willReturn($hub);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($hub, HubAdapter::getInstance()->setSpan($span));
    }
}
