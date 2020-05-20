<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\FulfilledPromise;
use Http\Promise\RejectedPromise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\Transport\HttpTransport;

final class HttpTransportTest extends TestCase
{
    public function testSendThrowsIfDsnOptionIsNotSet(): void
    {
        $transport = new HttpTransport(
            new Options(),
            $this->createMock(HttpAsyncClientInterface::class),
            MessageFactoryDiscovery::find(),
            false
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DSN option must be set to use the "Sentry\Transport\HttpTransport" transport.');

        $transport->send(new Event());
    }

    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testSendDelaysExecutionUntilShutdown(): void
    {
        $promise = new FulfilledPromise('foo');

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            MessageFactoryDiscovery::find(),
            true
        );

        $this->assertAttributeEmpty('pendingRequests', $transport);

        $transport->send(new Event());

        $this->assertAttributeNotEmpty('pendingRequests', $transport);

        $transport->close();

        $this->assertAttributeEmpty('pendingRequests', $transport);
    }

    public function testSendDoesNotDelayExecutionUntilShutdownWhenConfiguredToNotDoIt(): void
    {
        $promise = new RejectedPromise(new \Exception());

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            MessageFactoryDiscovery::find(),
            false
        );

        $transport->send(new Event());
    }

    public function testSendLogsErrorMessageIfSendingFailed(): void
    {
        $exception = new \Exception('foo');
        $event = new Event();

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to send the event to Sentry. Reason: "foo".', ['exception' => $exception, 'event' => $event]);

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn(new RejectedPromise($exception));

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            MessageFactoryDiscovery::find(),
            false,
            true,
            $logger
        );

        $transport->send($event);
    }

    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testCloseLogsErrorMessageIfSendingFailed(): void
    {
        $exception = new \Exception('foo');
        $event1 = new Event();
        $event2 = new Event();

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('error')
            ->withConsecutive([
                'Failed to send the event to Sentry. Reason: "foo".',
                ['exception' => $exception, 'event' => $event1],
            ],
            [
                'Failed to send the event to Sentry. Reason: "foo".',
                ['exception' => $exception, 'event' => $event2],
            ]);

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->exactly(2))
            ->method('sendAsyncRequest')
            ->willReturnOnConsecutiveCalls(
                new RejectedPromise($exception),
                new RejectedPromise($exception)
            );

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            MessageFactoryDiscovery::find(),
            true,
            true,
            $logger
        );

        // Send multiple events to assert that they all gets the chance of
        // being sent regardless of which fails
        $transport->send($event1);
        $transport->send($event2);
        $transport->close();
    }

    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testCloseLogsErrorMessageIfExceptionIsThrownWhileProcessingTheHttpRequest(): void
    {
        $exception = new \Exception('foo');

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to send the event to Sentry. Reason: "foo".', ['exception' => $exception]);

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willThrowException($exception);

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            MessageFactoryDiscovery::find(),
            true,
            true,
            $logger
        );

        $transport->send(new Event());
        $transport->close();
    }
}
