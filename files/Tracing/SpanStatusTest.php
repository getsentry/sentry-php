<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\SpanStatus;

final class SpanStatusTest extends TestCase
{
    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(SpanStatus $spanStatus, string $expectedStringRepresentation): void
    {
        $this->assertSame($expectedStringRepresentation, (string) $spanStatus);
    }

    public static function toStringDataProvider(): iterable
    {
        yield [
            SpanStatus::unauthenticated(),
            'unauthenticated',
        ];

        yield [
            SpanStatus::permissionDenied(),
            'permission_denied',
        ];

        yield [
            SpanStatus::notFound(),
            'not_found',
        ];

        yield [
            SpanStatus::alreadyExists(),
            'already_exists',
        ];

        yield [
            SpanStatus::failedPrecondition(),
            'failed_precondition',
        ];

        yield [
            SpanStatus::resourceExhausted(),
            'resource_exhausted',
        ];

        yield [
            SpanStatus::unimplemented(),
            'unimplemented',
        ];

        yield [
            SpanStatus::unavailable(),
            'unavailable',
        ];

        yield [
            SpanStatus::deadlineExceeded(),
            'deadline_exceeded',
        ];

        yield [
            SpanStatus::ok(),
            'ok',
        ];

        yield [
            SpanStatus::invalidArgument(),
            'invalid_argument',
        ];

        yield [
            SpanStatus::internalError(),
            'internal_error',
        ];

        yield [
            SpanStatus::unknownError(),
            'unknown_error',
        ];
    }

    /**
     * @dataProvider createFromHttpStatusCodeDataProvider
     */
    public function testCreateFromHttpStatusCode(SpanStatus $expectedSpanStatus, int $httpStatusCode): void
    {
        $this->assertSame($expectedSpanStatus, SpanStatus::createFromHttpStatusCode($httpStatusCode));
    }

    public static function createFromHttpStatusCodeDataProvider(): iterable
    {
        yield [
            SpanStatus::unauthenticated(),
            401,
        ];

        yield [
            SpanStatus::permissionDenied(),
            403,
        ];

        yield [
            SpanStatus::notFound(),
            404,
        ];

        yield [
            SpanStatus::alreadyExists(),
            409,
        ];

        yield [
            SpanStatus::failedPrecondition(),
            413,
        ];

        yield [
            SpanStatus::resourceExchausted(),
            429,
        ];

        yield [
            SpanStatus::unimplemented(),
            501,
        ];

        yield [
            SpanStatus::unavailable(),
            503,
        ];

        yield [
            SpanStatus::deadlineExceeded(),
            504,
        ];

        yield [
            SpanStatus::ok(),
            200,
        ];

        yield [
            SpanStatus::invalidArgument(),
            400,
        ];

        yield [
            SpanStatus::internalError(),
            500,
        ];

        yield [
            SpanStatus::unknownError(),
            600,
        ];
    }

    public function testStrictComparison(): void
    {
        $responseStatus1 = SpanStatus::ok();
        $responseStatus2 = SpanStatus::ok();
        $responseStatus3 = SpanStatus::unknownError();

        $this->assertSame($responseStatus1, $responseStatus2);
        $this->assertNotSame($responseStatus1, $responseStatus3);
    }
}
