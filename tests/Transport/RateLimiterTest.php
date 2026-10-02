<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sentry\EventType;
use Sentry\HttpClient\Response;
use Sentry\Tests\TestUtil\ClockMock;
use Sentry\Transport\RateLimiter;

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

        yield 'Back-off on 429 response without rate limit headers should lock them all' => [
            new Response(429, [], ''),
            true,
            EventType::cases(),
        ];

        yield 'Do not back-off on error response without rate limit headers' => [
            new Response(500, [], ''),
            false,
        ];
    }

    public function testHandleResponseWithMultipleCommaSpaceSeparatedLimits(): void
    {
        ClockMock::withClockMock(1644105600);

        // Relay joins multiple rate limits with ", "
        $this->rateLimiter->handleResponse(new Response(429, ['X-Sentry-Rate-Limits' => ['60:transaction:key, 2700:default;error;security:organization']], ''));

        $this->assertSame(1644105600 + 60, $this->rateLimiter->getDisabledUntil(EventType::transaction()));
        $this->assertSame(1644105600 + 2700, $this->rateLimiter->getDisabledUntil(EventType::event()));
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

    /**
     * @dataProvider getDisabledUntilDataProvider
     *
     * @param Response[] $responses
     */
    public function testGetDisabledUntil(array $responses, EventType $eventType, int $expectedDisabledUntil): void
    {
        ClockMock::withClockMock(1644105600);

        foreach ($responses as $response) {
            $this->rateLimiter->handleResponse($response);
        }

        $this->assertSame($expectedDisabledUntil, $this->rateLimiter->getDisabledUntil($eventType));
    }

    public static function getDisabledUntilDataProvider(): \Generator
    {
        yield 'Keep the longest limit of a category within the same header' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['2700:default;error;security:organization, 60:error:key']], ''),
            ],
            EventType::event(),
            1644105600 + 2700,
        ];

        yield 'Keep the longest limit of a category across responses' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['2700:error:organization']], ''),
                new Response(429, ['X-Sentry-Rate-Limits' => ['60:error:key']], ''),
            ],
            EventType::event(),
            1644105600 + 2700,
        ];

        yield 'Extend the limit of a category if a longer one is received' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['60:error:key']], ''),
                new Response(429, ['X-Sentry-Rate-Limits' => ['2700:error:organization']], ''),
            ],
            EventType::event(),
            1644105600 + 2700,
        ];

        yield 'Keep the longest limit of all categories across Retry-After headers' => [
            [
                new Response(429, ['Retry-After' => ['2700']], ''),
                new Response(429, ['Retry-After' => ['10']], ''),
            ],
            EventType::event(),
            1644105600 + 2700,
        ];

        yield 'Back-off for the default duration on 429 response without rate limit headers' => [
            [
                new Response(429, [], ''),
            ],
            EventType::transaction(),
            1644105600 + 60,
        ];

        yield 'Round up floating point retry_after' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['2700.5:error:organization']], ''),
            ],
            EventType::event(),
            1644105600 + 2701,
        ];

        yield 'Fall back to the default duration for invalid retry_after' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['foo:error:organization']], ''),
            ],
            EventType::event(),
            1644105600 + 60,
        ];

        yield 'Ignore empty limits' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['60:error:organization,']], ''),
            ],
            EventType::transaction(),
            0,
        ];

        yield 'Apply limits without categories to all categories' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['10']], ''),
            ],
            EventType::transaction(),
            1644105600 + 10,
        ];

        yield 'Apply limits following a limit without categories' => [
            [
                new Response(429, ['X-Sentry-Rate-Limits' => ['60:error:organization, 10, 2700:transaction:key']], ''),
            ],
            EventType::transaction(),
            1644105600 + 2700,
        ];
    }

    private function assertEventTypesAreRateLimited(array $eventTypesLimited): void
    {
        foreach ($eventTypesLimited as $eventType) {
            $this->assertTrue($this->rateLimiter->isRateLimited((string) $eventType));
        }

        $eventTypesNotLimited = array_diff(EventType::cases(), $eventTypesLimited);

        foreach ($eventTypesNotLimited as $eventType) {
            $this->assertFalse($this->rateLimiter->isRateLimited((string) $eventType));
        }
    }
}
