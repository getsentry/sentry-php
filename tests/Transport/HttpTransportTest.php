<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectionException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Promise\FulfilledPromise as HttpFullfilledPromise;
use Http\Promise\RejectedPromise as HttpRejectedPromise;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Sentry\Dsn;
use Sentry\Event;
use Sentry\Options;
use Sentry\ResponseStatus;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\HttpTransport;
use function GuzzleHttp\Psr7\stream_for;

final class HttpTransportTest extends TestCase
{
    /**
     * @var MockObject&HttpAsyncClientInterface
     */
    private $httpClient;

    /**
     * @var MockObject&StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var MockObject&RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var MockObject&PayloadSerializerInterface
     */
    private $payloadSerializer;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->payloadSerializer = $this->createMock(PayloadSerializerInterface::class);
    }

    public function testSendThrowsIfDsnOptionIsNotSet(): void
    {
        $transport = new HttpTransport(
            new Options(),
            $this->httpClient,
            $this->streamFactory,
            $this->requestFactory,
            $this->payloadSerializer
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The DSN option must be set to use the "Sentry\Transport\HttpTransport" transport.');

        $transport->send(Event::createEvent());
    }

    public function testSendTransactionAsEnvelope(): void
    {
        $dsn = Dsn::createFromString('http://public@example.com/sentry/1');
        $event = Event::createTransaction();

        $this->payloadSerializer->expects($this->once())
            ->method('serialize')
            ->with($event)
            ->willReturn('{"foo":"bar"}');

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $dsn->getEnvelopeApiEndpointUrl())
            ->willReturn(new Request('POST', 'http://www.example.com'));

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"foo":"bar"}')
            ->willReturnCallback(static function (string $content): StreamInterface {
                return stream_for($content);
            });

        $this->httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->with($this->callback(function (Request $requestArg): bool {
                if ('application/x-sentry-envelope' !== $requestArg->getHeaderLine('Content-Type')) {
                    return false;
                }

                if ('{"foo":"bar"}' !== $requestArg->getBody()->getContents()) {
                    return false;
                }

                return true;
            }))
            ->willReturn(new HttpFullfilledPromise(new Response()));

        $transport = new HttpTransport(
            new Options(['dsn' => $dsn]),
            $this->httpClient,
            $this->streamFactory,
            $this->requestFactory,
            $this->payloadSerializer
        );

        $transport->send($event);
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(int $httpStatusCode, string $expectedPromiseStatus, ResponseStatus $expectedResponseStatus): void
    {
        $dsn = Dsn::createFromString('http://public@example.com/sentry/1');
        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
            ->method('serialize')
            ->with($event)
            ->willReturn('{"foo":"bar"}');

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"foo":"bar"}')
            ->willReturnCallback(static function (string $content): StreamInterface {
                return stream_for($content);
            });

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $dsn->getStoreApiEndpointUrl())
            ->willReturn(new Request('POST', 'http://www.example.com'));

        $this->httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->with($this->callback(function (Request $requestArg): bool {
                if ('application/json' !== $requestArg->getHeaderLine('Content-Type')) {
                    return false;
                }

                if ('{"foo":"bar"}' !== $requestArg->getBody()->getContents()) {
                    return false;
                }

                return true;
            }))
            ->willReturn(new HttpFullfilledPromise(new Response($httpStatusCode)));

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $this->httpClient,
            $this->streamFactory,
            $this->requestFactory,
            $this->payloadSerializer
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
        $dsn = Dsn::createFromString('http://public@example.com/sentry/1');
        $exception = new \Exception('foo');
        $event = Event::createEvent();

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to send the event to Sentry. Reason: "foo".', ['exception' => $exception, 'event' => $event]);

        $this->payloadSerializer->expects($this->once())
            ->method('serialize')
            ->with($event)
            ->willReturn('{"foo":"bar"}');

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('POST', $dsn->getStoreApiEndpointUrl())
            ->willReturn(new Request('POST', 'http://www.example.com'));

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"foo":"bar"}')
            ->willReturnCallback(static function (string $content): StreamInterface {
                return stream_for($content);
            });

        $this->httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn(new HttpRejectedPromise($exception));

        $transport = new HttpTransport(
            new Options(['dsn' => $dsn]),
            $this->httpClient,
            $this->streamFactory,
            $this->requestFactory,
            $this->payloadSerializer,
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

    public function testClose(): void
    {
        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/sentry/1']),
            $this->createMock(HttpAsyncClientInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(PayloadSerializerInterface::class)
        );

        $promise = $transport->close();

        $this->assertSame(PromiseInterface::FULFILLED, $promise->getState());
        $this->assertTrue($promise->wait());
    }
}
