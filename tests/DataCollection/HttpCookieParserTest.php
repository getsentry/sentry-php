<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpCookieParser;

final class HttpCookieParserTest extends TestCase
{
    public function testCookieHeadersAreSplitInOrder(): void
    {
        $this->assertSame([
            ['theme', 'dark'],
            ['lang', 'en'],
            ['lang', 'de'],
            ['jwt', 'eyJ='],
            ['empty', ''],
            ['', 'debug'],
            ['', 'bad'],
            ['session_id', 'secret'],
        ], HttpCookieParser::splitCookieHeaders([
            ' theme = dark ;lang=en; lang=de; jwt=eyJ=; empty=; debug; =bad;;=; ',
            'session_id=secret',
        ]));
    }

    public function testSetCookieHeadersAreSplitInOrder(): void
    {
        $this->assertSame([
            ['theme', 'dark'],
            ['theme', 'light'],
            ['', 'opaque'],
        ], HttpCookieParser::splitSetCookieHeaders([
            'theme=dark; Path=/; Expires=Wed, 09 Jun 2027 10:18:14 GMT',
            ' theme = light ; HttpOnly',
            'opaque; Secure',
            '; Path=/',
            '=',
        ]));
    }
}
