<?php

declare(strict_types=1);

namespace Sentry\Tests\Util;

use PHPUnit\Framework\TestCase;
use Sentry\Dsn;
use Sentry\Util\Http;

final class HttpTest extends TestCase
{
    /**
     * @dataProvider getRequestHeadersDataProvider
     */
    public function testGetRequestHeaders(Dsn $dsn, string $sdkIdentifier, string $sdkVersion, array $expectedResult): void
    {
        $this->assertSame($expectedResult, Http::getRequestHeaders($dsn, $sdkIdentifier, $sdkVersion));
    }

    public static function getRequestHeadersDataProvider(): \Generator
    {
        yield [
            Dsn::createFromString('http://public@example.com/1'),
            'sentry.sdk.identifier',
            '1.2.3',
            [
                'Content-Type: application/x-sentry-envelope',
                'X-Sentry-Auth: Sentry sentry_version=7, sentry_client=sentry.sdk.identifier/1.2.3, sentry_key=public',
            ],
        ];
    }

    /**
     * @dataProvider parseResponseHeadersDataProvider
     */
    public function testParseResponseHeaders(string $headerline, $expectedResult): void
    {
        $responseHeaders = [];

        Http::parseResponseHeaders($headerline, $responseHeaders);

        $this->assertSame($expectedResult, $responseHeaders);
    }

    public static function parseResponseHeadersDataProvider(): \Generator
    {
        yield [
            'Content-Type: application/json',
            [
                'Content-Type' => [
                    'application/json',
                ],
            ],
        ];

        yield [
            'X-Sentry-Rate-Limits: 60:transaction:key,2700:default;error;security:organization',
            [
                'X-Sentry-Rate-Limits' => [
                    '60:transaction:key,2700:default;error;security:organization',
                ],
            ],
        ];

        yield [
            'Invalid',
            [],
        ];
    }
}
