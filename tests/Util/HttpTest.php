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
}
