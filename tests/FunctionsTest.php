<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\Tracing\SamplingContext;
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

    public function testCaptureMessage(): void
    {
        $eventId = EventId::generate();

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureMessage')
            ->with('foo', Severity::debug())
            ->willReturn($eventId);

        SentrySdk::getCurrentHub()->bindClient($client);

        $this->assertSame($eventId, captureMessage('foo', Severity::debug()));
    }

    public function testCaptureException(): void
    {
        $eventId = EventId::generate();
        $exception = new \RuntimeException('foo');

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($exception)
            ->willReturn($eventId);

        SentrySdk::getCurrentHub()->bindClient($client);

        $this->assertSame($eventId, captureException($exception));
    }

    public function testCaptureEvent(): void
    {
        $event = Event::createEvent();

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($event)
            ->willReturn($event->getId());

        SentrySdk::getCurrentHub()->bindClient($client);

        $this->assertSame($event->getId(), captureEvent($event));
    }

    public function testCaptureLastError()
    {
        $eventId = EventId::generate();

        /** @var ClientInterface|MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureLastError')
            ->willReturn($eventId);

        SentrySdk::getCurrentHub()->bindClient($client);

        @trigger_error('foo', E_USER_NOTICE);

        $this->assertSame($eventId, captureLastError());
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
        $customSamplingContext = ['foo' => 'bar'];

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('getOptions')
            ->willReturn(new Options([
                'traces_sampler' => function (SamplingContext $samplingContext) use ($customSamplingContext): float {
                    $this->assertSame($customSamplingContext, $samplingContext->getAdditionalContext());

                    return 0.0;
                },
            ]));

        SentrySdk::getCurrentHub()->bindClient($client);

        $transaction = startTransaction($transactionContext, $customSamplingContext);

        $this->assertSame($transactionContext->getName(), $transaction->getName());
    }
}
