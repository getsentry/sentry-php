<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\EventType;
use Sentry\HttpClient\Response;
use Sentry\Transport\RateLimiter;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class RateLimiterTest extends TestCase
{
    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var RateLimiter
     */
    private $rateLimiter;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->rateLimiter = new RateLimiter($this->logger);
    }

    /**
     * @dataProvider handleResponseDataProvider
     */
    public function testHandleResponse(Event $event, Response $response, int $responseStatusCode): void
    {
        ClockMock::withClockMock(1644105600);

        $this->logger->expects($response->isSuccess() ? $this->never() : $this->once())
            ->method('warning')
            ->with('Rate limited exceeded for requests of type "event", backing off until "2022-02-06T00:01:00+00:00".', ['event' => $event]);

        $rateLimiterResponse = $this->rateLimiter->handleResponse($event, $response);

        $this->assertSame($responseStatusCode, $rateLimiterResponse->getStatusCode());
    }

    public static function handleResponseDataProvider(): \Generator
    {
        yield 'Rate limits headers missing' => [
            Event::createEvent(),
            new Response(200, [], ''),
            200,
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with single category' => [
            Event::createEvent(),
            new Response(429, ['X-Sentry-Rate-Limits' => '60:error:org'], ''),
            429,
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with multiple categories' => [
            Event::createEvent(),
            new Response(429, ['X-Sentry-Rate-Limits' => '60:error;transaction:org'], ''),
            429,
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with missing categories should lock them all' => [
            Event::createEvent(),
            new Response(429, ['X-Sentry-Rate-Limits' => '60::org'], ''),
            429,
        ];

        yield 'Back-off using Retry-After header with number-based value' => [
            Event::createEvent(),
            new Response(429, ['Retry-After' => '60'], ''),
            429,
        ];

        yield 'Back-off using Retry-After header with date-based value' => [
            Event::createEvent(),
            new Response(429, ['Retry-After' => 'Sun, 02 February 2022 00:01:00 GMT'], ''),
            429,
        ];
    }

    public function testIsRateLimited(): void
    {
        // Events should not be rate-limited at all
        ClockMock::withClockMock(1644105600);

        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::event()));
        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::transaction()));

        // Events should be rate-limited for 60 seconds, but transactions should
        // still be allowed to be sent
        $this->rateLimiter->handleResponse(Event::createEvent(), new Response(429, ['X-Sentry-Rate-Limits' => '60:error:org'], ''));

        $this->assertTrue($this->rateLimiter->isRateLimited(EventType::event()));
        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::transaction()));

        // Events should not be rate-limited anymore once the deadline expired
        ClockMock::withClockMock(1644105660);

        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::event()));
        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::transaction()));

        // Both events and transactions should be rate-limited if all categories
        // are
        $this->rateLimiter->handleResponse(Event::createTransaction(), new Response(429, ['X-Sentry-Rate-Limits' => '60:all:org'], ''));

        $this->assertTrue($this->rateLimiter->isRateLimited(EventType::event()));
        $this->assertTrue($this->rateLimiter->isRateLimited(EventType::transaction()));

        // Both events and transactions should not be rate-limited anymore once
        // the deadline expired
        ClockMock::withClockMock(1644105720);

        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::event()));
        $this->assertFalse($this->rateLimiter->isRateLimited(EventType::transaction()));
    }
}
