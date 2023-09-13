<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Response;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\ResultStatus;
use Symfony\Bridge\PhpUnit\ClockMock;

final class HttpTransportTest extends TestCase
{
    /**
     * @var MockObject&HttpAsyncClientInterface
     */
    private $httpClient;

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
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->payloadSerializer = $this->createMock(PayloadSerializerInterface::class);
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(int $httpStatusCode, ResultStatus $expectedResultStatus, bool $expectEventReturned): void
    {
        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
            ->method('serialize')
            ->with($event)
            ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response($httpStatusCode, [], ''));

        $transport = new HttpTransport(
            $this->httpClient,
            $this->payloadSerializer
        );

        $result = $transport->send($event);

        $this->assertSame($expectedResultStatus, $result->getStatus());
        if ($expectEventReturned) {
            $this->assertSame($event, $result->getEvent());
        }
    }

    public static function sendDataProvider(): iterable
    {
        yield [
            200,
            ResultStatus::success(),
            true,
        ];

        yield [
            401,
            ResultStatus::invalid(),
            false,
        ];

        yield [
            429,
            ResultStatus::rateLimit(),
            false,
        ];

        yield [
            500,
            ResultStatus::failed(),
            false,
        ];
    }

    public function testSendFailsDueToHttpClientException(): void
    {
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

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->will($this->throwException($exception));

        $transport = new HttpTransport(
            $this->httpClient,
            $this->payloadSerializer,
            $logger
        );

        $result = $transport->send($event);

        $this->assertSame(ResultStatus::failed(), $result->getStatus());
    }

    /**
     * @group time-sensitive
     */
    public function testSendFailsDueToExceedingRateLimits(): void
    {
        ClockMock::withClockMock(1644105600);

        $event = Event::createEvent();

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('warning')
            ->withConsecutive(
                ['Rate limited exceeded for requests of type "event", backing off until "2022-02-06T00:01:00+00:00".', ['event' => $event]],
                ['Rate limit exceeded for sending requests of type "event".', ['event' => $event]]
            );

        $this->payloadSerializer->expects($this->once())
            ->method('serialize')
            ->with($event)
            ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(429, ['Retry-After' => '60'], ''));

        $transport = new HttpTransport(
            $this->httpClient,
            $this->payloadSerializer,
            $logger
        );

        // Event should be sent, but the server should reply with a HTTP 429
        $result = $transport->send($event);

        $this->assertSame(ResultStatus::rateLimit(), $result->getStatus());

        // Event should not be sent at all because rate-limit is in effect
        $result = $transport->send($event);

        $this->assertSame(ResultStatus::rateLimit(), $result->getStatus());
    }

    public function testClose(): void
    {
        $transport = new HttpTransport(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(PayloadSerializerInterface::class)
        );

        $result = $transport->close();

        $this->assertSame(ResultStatus::success(), $result->getStatus());
    }
}
