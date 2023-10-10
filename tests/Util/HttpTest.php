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
                'Content-Type' => 'application/x-sentry-envelope',
                'X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_client=sentry.sdk.identifier/1.2.3, sentry_key=public',
            ],
        ];

        yield [
            Dsn::createFromString('http://public:secret@example.com/1'),
            'sentry.sdk.identifier',
            '1.2.3',
            [
                'Content-Type' => 'application/x-sentry-envelope',
                'X-Sentry-Auth' => 'Sentry sentry_version=7, sentry_client=sentry.sdk.identifier/1.2.3, sentry_key=public, sentry_secret=secret',
            ],
        ];
    }

    /**
     * @dataProvider getResponseHeadersDataProvider
     */
    public function testGetResponseHeaders(?int $headerSize, string $body, array $expectedResult): void
    {
        $this->assertSame($expectedResult, Http::getResponseHeaders($headerSize, $body));
    }

    public static function getResponseHeadersDataProvider(): \Generator
    {
        yield [
            128,
            <<<TEXT
HTTP/1.1 200 OK\r\n
Server: nginx\r\n
Date: Tue, 10 Oct 2023 10:00:00 GMT\r\n
Content-Type: application/json\r\n
Content-Length: 41\r\n
\r\n
{"id":"2beb84919c3b4c92855206dd7f911a56"}
TEXT
            ,
            [
                'Server' => ['nginx'],
                'Date' => ['Tue, 10 Oct 2023 10:00:00 GMT'],
                'Content-Type' => ['application/json'],
                'Content-Length' => ['41'],
            ],
        ];
    }
}
