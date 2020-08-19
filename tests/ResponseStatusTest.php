<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ResponseStatus;

final class ResponseStatusTest extends TestCase
{
    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(ResponseStatus $responseStatus, string $expectedStringRepresentation): void
    {
        $this->assertSame($expectedStringRepresentation, (string) $responseStatus);
    }

    public function toStringDataProvider(): iterable
    {
        yield [
            ResponseStatus::success(),
            'SUCCESS',
        ];

        yield [
            ResponseStatus::failed(),
            'FAILED',
        ];

        yield [
            ResponseStatus::invalid(),
            'INVALID',
        ];

        yield [
            ResponseStatus::skipped(),
            'SKIPPED',
        ];

        yield [
            ResponseStatus::rateLimit(),
            'RATE_LIMIT',
        ];

        yield [
            ResponseStatus::unknown(),
            'UNKNOWN',
        ];
    }

    /**
     * @dataProvider createFromHttpStatusCodeDataProvider
     */
    public function testCreateFromHttpStatusCode(ResponseStatus $expectedResponseStatus, int $httpStatusCode): void
    {
        $this->assertSame($expectedResponseStatus, ResponseStatus::createFromHttpStatusCode($httpStatusCode));
    }

    public function createFromHttpStatusCodeDataProvider(): iterable
    {
        yield [
            ResponseStatus::success(),
            200,
        ];

        yield [
            ResponseStatus::success(),
            299,
        ];

        yield [
            ResponseStatus::rateLimit(),
            429,
        ];

        yield [
            ResponseStatus::invalid(),
            400,
        ];

        yield [
            ResponseStatus::invalid(),
            499,
        ];

        yield [
            ResponseStatus::failed(),
            500,
        ];

        yield [
            ResponseStatus::failed(),
            501,
        ];

        yield [
            ResponseStatus::unknown(),
            199,
        ];
    }

    public function testStrictComparison(): void
    {
        $responseStatus1 = ResponseStatus::unknown();
        $responseStatus2 = ResponseStatus::unknown();
        $responseStatus3 = ResponseStatus::skipped();

        $this->assertSame($responseStatus1, $responseStatus2);
        $this->assertNotSame($responseStatus1, $responseStatus3);
    }
}
