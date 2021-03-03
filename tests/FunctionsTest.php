<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use function Sentry\addBreadcrumb;
use function Sentry\captureEvent;
use function Sentry\captureException;
use function Sentry\captureLastError;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\init;
use function Sentry\startTransaction;
use function Sentry\withScope;

final class FunctionsTest extends TestCase
{
    public function testInit(): void
    {
        init(['default_integrations' => false]);

        $this->assertNotNull(SentrySdk::getCurrentHub()->getClient());
    }

    /**
     * @dataProvider captureMessageDataProvider
     */
    public function testCaptureMessage(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureMessage')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, captureMessage(...$functionCallArgs));
    }

    public function captureMessageDataProvider(): \Generator
    {
        yield [
            [
                'foo',
                Severity::debug(),
            ],
            [
                'foo',
                Severity::debug(),
                null,
            ],
        ];

        yield [
            [
                'foo',
                Severity::debug(),
                new EventHint(),
            ],
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
    public function testCaptureException(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureException')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($eventId, captureException(...$functionCallArgs));
    }

    public function captureExceptionDataProvider(): \Generator
    {
        yield [
            [
                new \Exception('foo'),
            ],
            [
                new \Exception('foo'),
                null,
            ],
        ];

        yield [
            [
                new \Exception('foo'),
                new EventHint(),
            ],
            [
                new \Exception('foo'),
                new EventHint(),
            ],
        ];
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent();
        $hint = new EventHint();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($event, $hint)
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), captureEvent($event, $hint));
    }

    /**
     * @dataProvider captureLastErrorDataProvider
     */
    public function testCaptureLastError(array $functionCallArgs, array $expectedFunctionCallArgs): void
    {
        $eventId = EventId::generate();

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureLastError')
            ->with(...$expectedFunctionCallArgs)
            ->willReturn($eventId);

        SentrySdk::setCurrentHub($hub);

        @trigger_error('foo', \E_USER_NOTICE);

        $this->assertSame($eventId, captureLastError(...$functionCallArgs));
    }

    public function captureLastErrorDataProvider(): \Generator
    {
        yield [
            [],
            [null],
        ];

        yield [
            [new EventHint()],
            [new EventHint()],
        ];
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options(['default_integrations' => false]));

        SentrySdk::getCurrentHub()->bindClient($client);

        addBreadcrumb($breadcrumb);
        configureScope(function (Scope $scope) use ($breadcrumb): void {
            $event = $scope->applyToEvent(Event::createEvent());

            $this->assertNotNull($event);
            $this->assertSame([$breadcrumb], $event->getBreadcrumbs());
        });
    }

    public function testWithScope(): void
    {
        $callbackInvoked = false;

        withScope(static function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testConfigureScope(): void
    {
        $callbackInvoked = false;

        configureScope(static function () use (&$callbackInvoked): void {
            $callbackInvoked = true;
        });

        $this->assertTrue($callbackInvoked);
    }

    public function testStartTransaction(): void
    {
        $transactionContext = new TransactionContext('foo');
        $transaction = new Transaction($transactionContext);
        $customSamplingContext = ['foo' => 'bar'];

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('startTransaction')
            ->with($transactionContext, $customSamplingContext)
            ->willReturn($transaction);

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($transaction, startTransaction($transactionContext, $customSamplingContext));
    }
}
