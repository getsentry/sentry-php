<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\FulfilledPromise;
use Http\Promise\RejectedPromise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Sentry\Event;
use Sentry\Exception\MissingProjectIdCredentialException;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\PendingRequest;

final class HttpTransportTest extends TestCase
{
    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testSendDelaysExecutionUntilShutdown(): void
    {
        $promise = new FulfilledPromise('foo');

        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $config = new Options(['dsn' => 'http://public@example.com/sentry/1']);
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find());

        $this->assertAttributeEmpty('pendingRequests', $transport);

        $transport->send(new Event());

        $this->assertAttributeNotEmpty('pendingRequests', $transport);

        $transport->close();

        $this->assertAttributeEmpty('pendingRequests', $transport);
    }

    public function testSendDoesNotDelayExecutionUntilShutdownWhenConfiguredToNotDoIt(): void
    {
        $promise = new RejectedPromise(new \Exception());

        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $config = new Options(['dsn' => 'http://public@example.com/sentry/1']);
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find(), false);

        $transport->send(new Event());

        $this->assertAttributeEmpty('pendingRequests', $transport);
    }

    public function testSendDoesReturnNullAndLogsErrorWhenSendingFailed(): void
    {
        $exception = new \Exception();
        $event = new Event();
        $promise = new RejectedPromise($exception);

        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $config = new Options(['dsn' => 'http://public@example.com/sentry/1']);
        $transport = new HttpTransport($config, $httpClient, MessageFactoryDiscovery::find(), false);
        $loggerProphecy = $this->prophesize(AbstractLogger::class);
        $loggerProphecy->error('Event could not be delivered', ['exception' => $exception, 'failedRequest' => new PendingRequest($promise, $event)])->shouldBeCalled();
        $transport->setLogger($loggerProphecy->reveal());

        $eventId = $transport->send($event);

        $this->assertAttributeEmpty('pendingRequests', $transport);
        // null is returned if sending request failed
        $this->assertNull($eventId);
    }

    public function testSendThrowsOnMissingProjectIdCredential(): void
    {
        $this->expectException(MissingProjectIdCredentialException::class);

        /** @var HttpAsyncClient&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);
        $transport = new HttpTransport(new Options(), $httpClient, MessageFactoryDiscovery::find(), false);

        $transport->send(new Event());
    }
}
