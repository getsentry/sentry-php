<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sentry\Transport\ResultStatus;

final class ResultStatusTest extends TestCase
{
    /**
     * @dataProvider toStringDataProvider
     */
    public function testToString(ResultStatus $responseStatus, string $expectedStringRepresentation): void
    {
        $this->assertSame($expectedStringRepresentation, (string) $responseStatus);
    }

    public static function toStringDataProvider(): iterable
    {
        yield [
            ResultStatus::success(),
            'SUCCESS',
        ];

        yield [
            ResultStatus::failed(),
            'FAILED',
        ];

        yield [
            ResultStatus::invalid(),
            'INVALID',
        ];

        yield [
            ResultStatus::skipped(),
            'SKIPPED',
        ];

        yield [
            ResultStatus::rateLimit(),
            'RATE_LIMIT',
        ];

        yield [
            ResultStatus::unknown(),
            'UNKNOWN',
        ];
    }

    /**
     * @dataProvider createFromHttpStatusCodeDataProvider
     */
    public function testCreateFromHttpStatusCode(ResultStatus $expectedResultStatus, int $httpStatusCode): void
    {
        $this->assertSame($expectedResultStatus, ResultStatus::createFromHttpStatusCode($httpStatusCode));
    }

    public static function createFromHttpStatusCodeDataProvider(): iterable
    {
        yield [
            ResultStatus::success(),
            200,
        ];

        yield [
            ResultStatus::success(),
            299,
        ];

        yield [
            ResultStatus::rateLimit(),
            429,
        ];

        yield [
            ResultStatus::invalid(),
            400,
        ];

        yield [
            ResultStatus::invalid(),
            499,
        ];

        yield [
            ResultStatus::failed(),
            500,
        ];

        yield [
            ResultStatus::failed(),
            501,
        ];

        yield [
            ResultStatus::unknown(),
            199,
        ];
    }

    public function testStrictComparison(): void
    {
        $responseStatus1 = ResultStatus::unknown();
        $responseStatus2 = ResultStatus::unknown();
        $responseStatus3 = ResultStatus::skipped();

        $this->assertSame($responseStatus1, $responseStatus2);
        $this->assertNotSame($responseStatus1, $responseStatus3);
    }
}
