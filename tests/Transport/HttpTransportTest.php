<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Response;
use Sentry\Options;
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
    public function testSend(Response $response, ResultStatus $expectedResultStatus, bool $expectEventReturned): void
    {
        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
                                ->method('serialize')
                                ->with($event)
                                ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
                         ->method('sendRequest')
                         ->willReturn($response);

        $transport = new HttpTransport(
            new Options([
                'dsn' => 'http://public@example.com/1',
            ]),
            $this->httpClient,
            $this->payloadSerializer
        );

        // We need to mock the time to ensure that the rate limiter works as expected and we can easily assert the log messages
        ClockMock::withClockMock(1644105600);

        $result = $transport->send($event);

        $this->assertSame($expectedResultStatus, $result->getStatus());
        if ($expectEventReturned) {
            $this->assertSame($event, $result->getEvent());
        }
    }

    public static function sendDataProvider(): iterable
    {
        yield [
            new Response(200, [], ''),
            ResultStatus::success(),
            true,
        ];

        yield [
            new Response(401, [], ''),
            ResultStatus::invalid(),
            false,
        ];

        ClockMock::withClockMock(1644105600);

        yield [
            new Response(429, ['Retry-After' => ['60']], ''),
            ResultStatus::rateLimit(),
            false,
        ];

        yield [
            new Response(500, [], ''),
            ResultStatus::failed(),
            false,
        ];
    }

    public function testSendFailsDueToHttpClientException(): void
    {
        $exception = new \Exception('foo');
        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
                                ->method('serialize')
                                ->with($event)
                                ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
                         ->method('sendRequest')
                         ->will($this->throwException($exception));

        $transport = new HttpTransport(
            new Options([
                'dsn' => 'http://public@example.com/1',
            ]),
            $this->httpClient,
            $this->payloadSerializer
        );

        $result = $transport->send($event);

        $this->assertSame(ResultStatus::failed(), $result->getStatus());
    }

    public function testSendFailsDueToCulrError(): void
    {
        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
                                ->method('serialize')
                                ->with($event)
                                ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
                         ->method('sendRequest')
                         ->willReturn(new Response(0, [], 'cURL Error (6) Could not resolve host: example.com'));

        $transport = new HttpTransport(
            new Options([
                'dsn' => 'http://public@example.com/1',
            ]),
            $this->httpClient,
            $this->payloadSerializer
        );

        $result = $transport->send($event);

        $this->assertSame(ResultStatus::unknown(), $result->getStatus());
    }

    /**
     * @group time-sensitive
     */
    public function testSendFailsDueToExceedingRateLimits(): void
    {
        ClockMock::withClockMock(1644105600);

        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
                                ->method('serialize')
                                ->with($event)
                                ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
                         ->method('sendRequest')
                         ->willReturn(new Response(429, ['Retry-After' => ['60']], ''));

        $transport = new HttpTransport(
            new Options([
                'dsn' => 'http://public@example.com/1',
            ]),
            $this->httpClient,
            $this->payloadSerializer
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
            new Options(),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(PayloadSerializerInterface::class)
        );

        $result = $transport->close();

        $this->assertSame(ResultStatus::success(), $result->getStatus());
    }
}
