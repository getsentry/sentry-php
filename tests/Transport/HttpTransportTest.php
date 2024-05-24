<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\Constraint\StringMatchesFormatDescription;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface&MockObject
     */
    private $logger;

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
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->payloadSerializer = $this->createMock(PayloadSerializerInterface::class);
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(Response $response, ResultStatus $expectedResultStatus, bool $expectEventReturned, array $expectedLogMessages): void
    {
        $event = Event::createEvent();

        $this->payloadSerializer->expects($this->once())
            ->method('serialize')
            ->with($event)
            ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($response);

        foreach ($expectedLogMessages as $level => $messages) {
            $this->logger->expects($this->exactly(\count($messages)))
                ->method($level)
                ->with($this->logicalOr(
                    ...array_map(function (string $message) {
                        return new StringMatchesFormatDescription($message);
                    }, $messages)
                ));
        }

        $transport = new HttpTransport(
            new Options([
                'dsn' => 'http://public@example.com/1',
            ]),
            $this->httpClient,
            $this->payloadSerializer,
            $this->logger
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
            [
                'info' => [
                    'Sending event [%s] to %s [project:%s].',
                    'Sent event [%s] to %s [project:%s]. Result: "success" (status: 200).',
                ],
            ],
        ];

        yield [
            new Response(401, [], ''),
            ResultStatus::invalid(),
            false,
            [
                'info' => [
                    'Sending event [%s] to %s [project:%s].',
                    'Sent event [%s] to %s [project:%s]. Result: "invalid" (status: 401).',
                ],
            ],
        ];

        ClockMock::withClockMock(1644105600);

        yield [
            new Response(429, ['Retry-After' => ['60']], ''),
            ResultStatus::rateLimit(),
            false,
            [
                'info' => [
                    'Sending event [%s] to %s [project:%s].',
                    'Sent event [%s] to %s [project:%s]. Result: "rate_limit" (status: 429).',
                ],
                'warning' => [
                    'Rate limited exceeded for all categories, backing off until "2022-02-06T00:01:00+00:00".',
                ],
            ],
        ];

        yield [
            new Response(500, [], ''),
            ResultStatus::failed(),
            false,
            [
                'info' => [
                    'Sending event [%s] to %s [project:%s].',
                    'Sent event [%s] to %s [project:%s]. Result: "failed" (status: 500).',
                ],
            ],
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
            ->with(
                new StringMatchesFormatDescription('Failed to send event [%s] to %s [project:%s]. Reason: "foo".'),
                ['exception' => $exception, 'event' => $event]
            );

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
            $this->payloadSerializer,
            $logger
        );

        $result = $transport->send($event);

        $this->assertSame(ResultStatus::failed(), $result->getStatus());
    }

    public function testSendFailsDueToCulrError(): void
    {
        $event = Event::createEvent();

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                new StringMatchesFormatDescription('Failed to send event [%s] to %s [project:%s]. Reason: "cURL Error (6) Could not resolve host: example.com".'),
                ['event' => $event]
            );

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
            $this->payloadSerializer,
            $logger
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

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('warning')
            ->withConsecutive(
                ['Rate limited exceeded for all categories, backing off until "2022-02-06T00:01:00+00:00".'],
                ['Rate limit exceeded for sending requests of type "event".', ['event' => $event]]
            );

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
            new Options(),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(PayloadSerializerInterface::class)
        );

        $result = $transport->close();

        $this->assertSame(ResultStatus::success(), $result->getStatus());
    }
}
