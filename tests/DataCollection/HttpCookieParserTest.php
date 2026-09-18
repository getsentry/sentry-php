<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpCookieParser;

final class HttpCookieParserTest extends TestCase
{
    public function testCookieHeadersPreserveNamesAndValuesWithLastValueWinning(): void
    {
        $cookies = HttpCookieParser::parseCookieHeaders([
            'theme=dark; session_id=secret; empty=',
            'theme=light; encoded=a%20b+c; padded=abc==; quoted="value"; Theme=other; 123=numeric',
        ]);

        $this->assertSame([
            ['theme', 'light'], ['session_id', 'secret'], ['empty', ''],
            ['encoded', 'a%20b+c'], ['padded', 'abc=='], ['quoted', '"value"'], ['Theme', 'other'], ['123', 'numeric'],
        ], $cookies);
    }

    public function testCookieHeadersTrimOnlySurroundingFragmentWhitespace(): void
    {
        $this->assertSame([
            ['theme ', ' dark'],
        ], HttpCookieParser::parseCookieHeaders(['  theme = dark  ']));
    }

    public function testSetCookieHeadersExcludeAttributesAndPreserveRepeatedNames(): void
    {
        $this->assertSame([
            ['theme', 'dark'],
            ['session_id', 'secret'],
            ['theme', 'light'],
            ['empty', ''],
        ], HttpCookieParser::parseSetCookieHeaders([
            'theme=dark; Path=/; Expires=Wed, 09 Jun 2027 10:18:14 GMT',
            'session_id=secret; Secure; HttpOnly; SameSite=Lax',
            'theme=light; Path=/other',
            'empty=; Max-Age=0',
        ]));
    }

    public function testSetCookieHeadersTrimOnlyCookieNames(): void
    {
        $this->assertSame([
            ['theme', ' dark  '],
        ], HttpCookieParser::parseSetCookieHeaders(['  theme = dark  ; Path=/']));
    }

    /**
     * @dataProvider nonStandardCookieProvider
     */
    public function testCookieNamesAndValuesAreNotValidated(string $name, string $value): void
    {
        $expected = [
            ['theme', 'dark'],
            [$name, $value],
            ['session_id', 'secret'],
        ];

        $this->assertSame($expected, HttpCookieParser::parseCookieHeaders([
            'theme=dark; ' . $name . '=' . $value . '; session_id=secret',
        ]));
        $this->assertSame($expected, HttpCookieParser::parseSetCookieHeaders([
            'theme=dark; Path=/',
            $name . '=' . $value . '; Path=/; HttpOnly',
            'session_id=secret; Secure',
        ]));
    }

    public function nonStandardCookieProvider(): \Generator
    {
        yield 'unencoded space' => ['display_name', 'Alice Smith'];
        yield 'raw JSON' => ['preferences', '{"theme":"dark", "layout":"compact"}'];
        yield 'non-ASCII text' => ['category', 'café'];
        yield 'Windows path' => ['download_path', 'C:\Users\alice'];
        yield 'comma-separated values' => ['cart', 'cart-123,cart-456'];
        yield 'unclosed quote' => ['appearance', '"dark'];
        yield 'space in name' => ['display name', 'Alice'];
    }

    public function testAbsentHeadersProduceNoCookies(): void
    {
        $this->assertSame([], HttpCookieParser::parseCookieHeaders([]));
        $this->assertSame([], HttpCookieParser::parseSetCookieHeaders([]));
    }

    /**
     * @dataProvider unparseablePairProvider
     */
    public function testUnparseableHeadersAreSkipped(string $header): void
    {
        $expected = [['theme', 'dark'], ['language', 'en']];

        $this->assertSame($expected, HttpCookieParser::parseCookieHeaders(['theme=dark', $header, 'language=en']));
        $this->assertSame($expected, HttpCookieParser::parseSetCookieHeaders(['theme=dark', $header, 'language=en']));
    }

    public function unparseablePairProvider(): \Generator
    {
        yield 'empty header' => [''];
        yield 'no value separator' => ['opaque'];
        yield 'missing name' => ['  =secret'];
    }

    public function testUnparseableRequestPartsAreSkipped(): void
    {
        $this->assertSame([
            ['empty', ''],
            ['user_session', 'secret'],
            ['theme', 'dark'],
        ], HttpCookieParser::parseCookieHeaders(['debug; =bad; empty=; user_session=secret; theme=dark;;']));
    }
}
