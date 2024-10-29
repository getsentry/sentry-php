<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\TestCase;
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
     * @var RateLimiter
     */
    private $rateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * @dataProvider handleResponseDataProvider
     */
    public function testHandleResponse(Response $response, bool $shouldBeHandled, array $eventTypesLimited = []): void
    {
        ClockMock::withClockMock(1644105600);

        $this->rateLimiter->handleResponse($response);
        $this->assertEventTypesAreRateLimited($eventTypesLimited);
    }

    public static function handleResponseDataProvider(): \Generator
    {
        yield 'Rate limits headers missing' => [
            new Response(200, [], ''),
            false,
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with single category' => [
            new Response(429, ['X-Sentry-Rate-Limits' => ['60:error:org']], ''),
            true,
            [
                EventType::event(),
            ],
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with multiple categories' => [
            new Response(429, ['X-Sentry-Rate-Limits' => ['60:error;transaction;metric_bucket:org']], ''),
            true,
            [
                EventType::event(),
                EventType::transaction(),
            ],
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with missing categories should lock them all' => [
            new Response(429, ['X-Sentry-Rate-Limits' => ['60::org']], ''),
            true,
            EventType::cases(),
        ];

        yield 'Do not back-off using X-Sentry-Rate-Limits header with metric_bucket category, namespace foo' => [
            new Response(429, ['X-Sentry-Rate-Limits' => ['60:metric_bucket:organization:quota_exceeded:foo']], ''),
            false,
            [],
        ];

        yield 'Back-off using Retry-After header with number-based value' => [
            new Response(429, ['Retry-After' => ['60']], ''),
            true,
            EventType::cases(),
        ];

        yield 'Back-off using Retry-After header with date-based value' => [
            new Response(429, ['Retry-After' => ['Sun, 02 February 2022 00:01:00 GMT']], ''),
            true,
            EventType::cases(),
        ];
    }

    public function testIsRateLimited(): void
    {
        // Events should not be rate-limited at all
        ClockMock::withClockMock(1644105600);

        $this->assertEventTypesAreRateLimited([]);

        // Events should be rate-limited for 60 seconds, but transactions should still be allowed to be sent
        $this->rateLimiter->handleResponse(new Response(429, ['X-Sentry-Rate-Limits' => ['60:error:org']], ''));

        $this->assertEventTypesAreRateLimited([EventType::event()]);

        // Events should not be rate-limited anymore once the deadline expired
        ClockMock::withClockMock(1644105660);

        $this->assertEventTypesAreRateLimited([]);

        // Both events and transactions should be rate-limited if all categories are
        $this->rateLimiter->handleResponse(new Response(429, ['X-Sentry-Rate-Limits' => ['60:all:org']], ''));

        $this->assertEventTypesAreRateLimited(EventType::cases());

        // Both events and transactions should not be rate-limited anymore once the deadline expired
        ClockMock::withClockMock(1644105720);

        $this->assertEventTypesAreRateLimited([]);
    }

    private function assertEventTypesAreRateLimited(array $eventTypesLimited): void
    {
        foreach ($eventTypesLimited as $eventType) {
            $this->assertTrue($this->rateLimiter->isRateLimited($eventType));
        }

        $eventTypesNotLimited = array_diff(EventType::cases(), $eventTypesLimited);

        foreach ($eventTypesNotLimited as $eventType) {
            $this->assertFalse($this->rateLimiter->isRateLimited($eventType));
        }
    }
}
