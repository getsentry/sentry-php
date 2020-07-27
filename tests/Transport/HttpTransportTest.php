<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectionException;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Mock\Client as HttpMockClient;
use Http\Promise\FulfilledPromise as HttpFullfilledPromise;
use Http\Promise\RejectedPromise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\Options;
use Sentry\ResponseStatus;
use Sentry\Transport\HttpTransport;

final class HttpTransportTest extends TestCase
{
    public function testSendThrowsIfDsnOptionIsNotSet(): void
    {
        $transport = new HttpTransport(
            new Options(),
            $this->createMock(HttpAsyncClientInterface::class),
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findRequestFactory()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DSN option must be set to use the "Sentry\Transport\HttpTransport" transport.');

        $transport->send(new Event());
    }

    public function testSendTransactionAsEnvelope(): void
    {
        $mockHttpClient = new HttpMockClient();
        $httpClientFactory = new HttpClientFactory(
            Psr17FactoryDiscovery::findUrlFactory(),
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            $mockHttpClient,
            'sentry.php.test',
            '1.2.3'
        );

        $httpClient = $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'default_integrations' => false,
        ]));

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findRequestFactory()
        );

        $event = new Event();
        $event->setType('transaction');

        $transport->send($event);

        $httpRequest = $mockHttpClient->getLastRequest();

        $this->assertSame('application/x-sentry-envelope', $httpRequest->getHeaderLine('Content-Type'));
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(int $httpStatusCode, string $expectedPromiseStatus, ResponseStatus $expectedResponseStatus): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($httpStatusCode);

        $event = new Event();

        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn(new HttpFullfilledPromise($response));

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $httpClient,
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findRequestFactory()
        );

        $promise = $transport->send($event);

        try {
            $promiseResult = $promise->wait();
        } catch (RejectionException $exception) {
            $promiseResult = $exception->getReason();
        }

        $this->assertSame($expectedPromiseStatus, $promise->getState());
        $this->assertSame($expectedResponseStatus, $promiseResult->getStatus());
        $this->assertSame($event, $promiseResult->getEvent());
    }

    public function sendDataProvider(): iterable
    {
        yield [
            200,
            PromiseInterface::FULFILLED,
            ResponseStatus::success(),
        ];

        yield [
            500,
            PromiseInterface::REJECTED,
            ResponseStatus::failed(),
        ];
    }

    public function testSendReturnsRejectedPromiseIfSendingFailedDueToHttpClientException(): void
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
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findRequestFactory(),
            $logger
        );

        $promise = $transport->send($event);

        try {
            $promiseResult = $promise->wait();
        } catch (RejectionException $exception) {
            $promiseResult = $exception->getReason();
        }

        $this->assertSame(PromiseInterface::REJECTED, $promise->getState());
        $this->assertSame(ResponseStatus::failed(), $promiseResult->getStatus());
        $this->assertSame($event, $promiseResult->getEvent());
    }
}
