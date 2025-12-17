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
use Sentry\Profiling\Profile;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Tests\TestUtil\ClockMock;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\ResultStatus;

final class HttpTransportTest extends TestCase
{
    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var MockObject&HttpClientInterface
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

    public function testSendFailsDueToCurlError(): void
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

    /**
     * @group time-sensitive
     */
    public function testDropsProfileAndSendsTransactionWhenProfileRateLimited(): void
    {
        ClockMock::withClockMock(1644105600);

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/1']),
            $this->httpClient,
            $this->payloadSerializer,
            $this->logger
        );

        $event = Event::createTransaction();
        $event->setSdkMetadata('profile', new Profile());

        $this->payloadSerializer->expects($this->exactly(2))
            ->method('serialize')
            ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(429, ['X-Sentry-Rate-Limits' => ['60:profile:key']], ''),
                new Response(200, [], '')
            );

        // First request is rate limited because of profiles
        $result = $transport->send($event);

        $this->assertEquals(ResultStatus::rateLimit(), $result->getStatus());

        // profile information is still present
        $this->assertNotNull($event->getSdkMetadata('profile'));

        $event = Event::createTransaction();
        $event->setSdkMetadata('profile', new Profile());

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Rate limit exceeded for sending requests of type "profile".'),
                ['event' => $event]
            );

        $result = $transport->send($event);

        // Sending transaction is successful because only profiles are rate limited
        $this->assertEquals(ResultStatus::success(), $result->getStatus());

        // profile information is removed because it was rate limited
        $this->assertNull($event->getSdkMetadata('profile'));
    }

    /**
     * @group time-sensitive
     */
    public function testCheckInsAreRateLimited(): void
    {
        ClockMock::withClockMock(1644105600);

        $transport = new HttpTransport(
            new Options(['dsn' => 'http://public@example.com/1']),
            $this->httpClient,
            $this->payloadSerializer,
            $this->logger
        );

        $event = Event::createCheckIn();

        $this->payloadSerializer->expects($this->exactly(1))
            ->method('serialize')
            ->willReturn('{"foo":"bar"}');

        $this->httpClient->expects($this->exactly(1))
            ->method('sendRequest')
            ->willReturn(
                new Response(429, ['X-Sentry-Rate-Limits' => ['60:monitor:key']], '')
            );

        $result = $transport->send($event);

        $this->assertEquals(ResultStatus::rateLimit(), $result->getStatus());

        $event = Event::createCheckIn();

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Rate limit exceeded for sending requests of type "check_in".'),
                ['event' => $event]
            );

        $result = $transport->send($event);

        $this->assertEquals(ResultStatus::rateLimit(), $result->getStatus());
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
