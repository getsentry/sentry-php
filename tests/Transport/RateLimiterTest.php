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
    }

    public function testHandleResponseWithMultipleCommaSpaceSeparatedLimits(): void
    {
        ClockMock::withClockMock(1644105600);

        // Relay joins multiple rate limits with ", "
        $this->rateLimiter->handleResponse(new Response(429, ['X-Sentry-Rate-Limits' => ['60:transaction:key, 2700:default;error;security:organization']], ''));

        $this->assertSame(1644105600 + 60, $this->rateLimiter->getDisabledUntil(EventType::transaction()));
        $this->assertSame(1644105600 + 2700, $this->rateLimiter->getDisabledUntil(EventType::event()));
    }

    /**
     * @dataProvider attachmentRateLimitsDataProvider
     */
    public function testAttachmentsAreRateLimited(string $rateLimitsHeader, int $expectedDisabledUntil): void
    {
        ClockMock::withClockMock(1644105600);

        $this->rateLimiter->handleResponse(new Response(429, ['X-Sentry-Rate-Limits' => [$rateLimitsHeader]], ''));

        $this->assertTrue($this->rateLimiter->isRateLimited(RateLimiter::DATA_CATEGORY_ATTACHMENT));
        $this->assertSame($expectedDisabledUntil, $this->rateLimiter->getDisabledUntil(RateLimiter::DATA_CATEGORY_ATTACHMENT));

        // Attachment rate limits must not affect the events the attachments belong to
        $this->assertEventTypesAreRateLimited([]);

        ClockMock::withClockMock($expectedDisabledUntil);

        $this->assertFalse($this->rateLimiter->isRateLimited(RateLimiter::DATA_CATEGORY_ATTACHMENT));
    }

    public static function attachmentRateLimitsDataProvider(): \Generator
    {
        yield 'Back-off using X-Sentry-Rate-Limits header with attachment category' => [
            '60:attachment:organization',
            1644105600 + 60,
        ];

        yield 'Back-off using X-Sentry-Rate-Limits header with attachment_item category' => [
            '60:attachment_item:organization',
            1644105600 + 60,
        ];

        yield 'Back-off using the longest of the attachment and attachment_item categories' => [
            '120:attachment_item:organization, 60:attachment:organization',
            1644105600 + 120,
        ];
    }

    public function testAttachmentsAreRateLimitedWhenAllCategoriesAreRateLimited(): void
    {
        ClockMock::withClockMock(1644105600);

        $this->rateLimiter->handleResponse(new Response(429, ['Retry-After' => ['60']], ''));

        $this->assertTrue($this->rateLimiter->isRateLimited(RateLimiter::DATA_CATEGORY_ATTACHMENT));
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
            $this->assertTrue($this->rateLimiter->isRateLimited((string) $eventType));
        }

        $eventTypesNotLimited = array_diff(EventType::cases(), $eventTypesLimited);

        foreach ($eventTypesNotLimited as $eventType) {
            $this->assertFalse($this->rateLimiter->isRateLimited((string) $eventType));
        }
    }
}
