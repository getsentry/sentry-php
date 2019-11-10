<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise as HttpPromiseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Exception\MissingProjectIdCredentialException;
use Sentry\Options;
use Sentry\Transport\HttpTransport;

final class HttpTransportTest extends TestCase
{
    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testSendDelaysExecutionUntilShutdown(): void
    {
        /** @var HttpPromiseInterface&MockObject $promise */
        $promise = $this->createMock(HttpPromiseInterface::class);

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            MessageFactoryDiscovery::find()
        );

        $transport->send(new Event());

        $promise->expects($this->once())
            ->method('wait');

        $this->assertTrue($transport->close()->wait());
    }

    public function testSendDoesNotDelayExecutionUntilShutdownWhenConfiguredToNotDoIt(): void
    {
        /** @var HttpPromiseInterface&MockObject $promise */
        $promise = $this->createMock(HttpPromiseInterface::class);
        $promise->expects($this->once())
            ->method('wait');

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

        $this->assertTrue($transport->close()->wait());
    }

    public function testSendThrowsOnMissingProjectIdCredential(): void
    {
        $this->expectException(MissingProjectIdCredentialException::class);

        $transport = new HttpTransport(
            new Options(),
            $this->createMock(HttpAsyncClientInterface::class),
            MessageFactoryDiscovery::find(),
            false
        );

        $transport->send(new Event());
    }

    /**
     * @group time-sensitive
     */
    public function testSendEventWithInvalidEncoding(): void
    {
        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn(new FulfilledPromise(null));

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/1']),
            $httpClient,
            MessageFactoryDiscovery::find(),
            false
        );

        $brokenString = "\x42\x65\x61\x75\x6d\x6f\x6e\x74\x2d\x65\x6e\x2d\x76\xe9\x72\x6f\x6e";

        $event = new Event();
        $event->setMessage($brokenString);
        $event->setBreadcrumb([
            new Breadcrumb(
                Breadcrumb::LEVEL_ERROR,
                Breadcrumb::TYPE_ERROR,
                'error_reporting',
                $brokenString
            )
        ]);

        $transport->send($event);
    }
}
