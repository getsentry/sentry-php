<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpSpanDataCollector;
use Sentry\Options;

final class HttpSpanDataCollectorTest extends TestCase
{
    public function testServerRequestIsCollected(): void
    {
        $request = (new ServerRequest('POST', 'https://example.com/', [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer secret',
            'X-Forwarded-For' => ['192.0.2.1', '198.51.100.1'],
            'Cookie' => 'theme=dark; session_id=secret',
        ], '{"name":"Alice","password":"secret"}'))->withCookieParams(['theme' => 'dark', 'session_id' => 'secret']);

        $this->assertSame([
            'http.request.header.host' => 'example.com',
            'http.request.header.content-type' => 'application/json',
            'http.request.header.authorization' => '[Filtered]',
            'http.request.header.x-forwarded-for' => '192.0.2.1, 198.51.100.1',
            'http.request.header.cookie' => ['theme=dark', 'session_id=[Filtered]'],
            'http.request.body.data' => '{"name":"Alice","password":"[Filtered]"}',
        ], HttpSpanDataCollector::collectServerRequest(self::policy(), $request));
    }

    /**
     * @param array<string, mixed>    $cookieParams
     * @param array<string, string[]> $expectedData
     *
     * @dataProvider serverRequestCookiesDataProvider
     */
    public function testServerRequestCookiesAreCollected(array $cookieParams, string $cookieHeader, array $expectedData): void
    {
        $request = (new ServerRequest('GET', '/', ['Cookie' => $cookieHeader]))->withCookieParams($cookieParams);

        $this->assertSame($expectedData, HttpSpanDataCollector::collectServerRequest(self::policy(['http_headers' => ['mode' => 'off']]), $request));
    }

    public static function serverRequestCookiesDataProvider(): \Generator
    {
        yield 'cookies parsed by the framework are preferred, since decoded values can contain ";"' => [
            ['theme' => 'dark; session_id=secret'],
            'theme=dark%3B%20session_id%3Dsecret',
            ['http.request.header.cookie' => ['theme=dark; session_id=secret']],
        ];

        yield 'the cookie header is parsed if the framework did not parse cookies' => [
            [],
            'theme=dark; session_id=secret',
            ['http.request.header.cookie' => ['theme=dark', 'session_id=[Filtered]']],
        ];

        yield 'nested cookies are filtered' => [
            ['preferences' => ['theme' => 'dark']],
            'preferences[theme]=dark',
            ['http.request.header.cookie' => ['preferences=[Filtered]']],
        ];
    }

    /**
     * @dataProvider serverRequestBodyDataProvider
     */
    public function testServerRequestBodyIsCollected(DataCollectionPolicy $policy, ServerRequestInterface $request, ?string $expectedBody): void
    {
        $this->assertSame($expectedBody, HttpSpanDataCollector::collectServerRequest($policy, $request)['http.request.body.data'] ?? null);
    }

    public static function serverRequestBodyDataProvider(): \Generator
    {
        yield 'form bodies are read from the parsed body' => [
            self::policy(),
            (new ServerRequest('POST', '/', ['Content-Type' => 'application/x-www-form-urlencoded']))->withParsedBody(['name' => 'Alice', 'password' => 'secret']),
            '{"name":"Alice","password":"[Filtered]"}',
        ];

        yield 'bodies that cannot be parsed are filtered' => [
            self::policy(),
            new ServerRequest('POST', '/', ['Content-Type' => 'text/plain'], 'Hello World'),
            '[Filtered]',
        ];

        yield 'bodies over the size limit are not collected' => [
            DataCollectionPolicy::fromOptions(new Options(['data_collection' => [], 'max_request_body_size' => 'small'])),
            new ServerRequest('POST', '/', ['Content-Type' => 'application/json', 'Content-Length' => '2000'], '{}'),
            null,
        ];

        yield 'disabled incoming request bodies are not collected' => [
            self::policy(['http_bodies' => ['outgoingRequest', 'incomingResponse', 'outgoingResponse']]),
            new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"name":"Alice"}'),
            null,
        ];
    }

    public function testPsr7RequestIsCollected(): void
    {
        $request = new Request('POST', 'https://example.com/', [
            'Content-Type' => 'application/json',
            'Cookie' => 'theme=dark; session_id=secret',
        ], '{"name":"Alice","password":"secret"}');

        $this->assertSame([
            'http.request.header.host' => 'example.com',
            'http.request.header.content-type' => 'application/json',
            'http.request.header.cookie' => ['theme=dark', 'session_id=[Filtered]'],
            'http.request.body.data' => '{"name":"Alice","password":"[Filtered]"}',
        ], HttpSpanDataCollector::collectPsr7Request(self::policy(), HttpMessageType::outgoingRequest(), $request));
    }

    public function testPsr7ResponseIsCollected(): void
    {
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'Set-Cookie' => ['theme=light; Path=/', 'session_id=secret; HttpOnly'],
        ], '{"status":"ok","token":"secret"}');

        $this->assertSame([
            'http.response.header.content-type' => 'application/json',
            'http.response.header.set-cookie' => ['theme=light', 'session_id=[Filtered]'],
            'http.response.body.data' => '{"status":"ok","token":"[Filtered]"}',
        ], HttpSpanDataCollector::collectPsr7Response(self::policy(), HttpMessageType::incomingResponse(), $response));
    }

    public function testHeadersAreCollected(): void
    {
        $this->assertSame([
            'http.response.header.x-served-by' => 'web-1, web-2',
            'http.response.header.x-api-key' => '[Filtered]',
            'http.response.header.123' => 'numeric',
        ], HttpSpanDataCollector::collectHeaders(self::policy(), HttpMessageType::outgoingResponse(), [
            'X-Served-By' => ['web-1', 'web-2'],
            'X-Api-Key' => ['secret'],
            'Set-Cookie' => ['theme=dark'],
            123 => ['numeric'],
        ]));
    }

    /**
     * @param string[]                $headers
     * @param array<string, string[]> $expectedData
     *
     * @dataProvider cookieHeadersDataProvider
     */
    public function testCookieHeadersAreCollected(HttpMessageType $type, array $headers, array $expectedData): void
    {
        $this->assertSame($expectedData, HttpSpanDataCollector::collectCookieHeaders(self::policy(), $type, $headers));
    }

    public static function cookieHeadersDataProvider(): \Generator
    {
        yield 'cookies are split on ";" and keep their order and duplicate names' => [
            HttpMessageType::outgoingRequest(),
            ['theme=dark;lang=en; lang=de', 'session_id=secret'],
            ['http.request.header.cookie' => ['theme=dark', 'lang=en', 'lang=de', 'session_id=[Filtered]']],
        ];

        yield 'names and values are split on the first "=" and trimmed, but not decoded' => [
            HttpMessageType::outgoingRequest(),
            [' theme = dark ; data=a=b; name=Caf%C3%A9'],
            ['http.request.header.cookie' => ['theme=dark', 'data=a=b', 'name=Caf%C3%A9']],
        ];

        yield 'empty segments are skipped and nameless cookies are filtered' => [
            HttpMessageType::outgoingRequest(),
            ['theme=dark; ;=; opaque-token; =value'],
            ['http.request.header.cookie' => ['theme=dark', '[Filtered]', '[Filtered]']],
        ];

        yield 'cookie headers without a cookie are filtered, since they might hold a token' => [
            HttpMessageType::outgoingRequest(),
            [';;='],
            ['http.request.header.cookie' => ['[Filtered]']],
        ];

        yield 'empty cookie headers are not collected' => [
            HttpMessageType::outgoingRequest(),
            ['', ' '],
            [],
        ];

        yield 'set-cookie headers drop the attributes of their cookie' => [
            HttpMessageType::incomingResponse(),
            ['theme=dark; Path=/; HttpOnly', 'lang=en; Expires=Wed, 21 Oct 2026 07:28:00 GMT', 'session_id=secret; Secure', 'opaque; HttpOnly'],
            ['http.response.header.set-cookie' => ['theme=dark', 'lang=en', 'session_id=[Filtered]', '[Filtered]']],
        ];
    }

    public function testCookiePairsAreCollected(): void
    {
        $this->assertSame(
            ['http.response.header.set-cookie' => ['theme=dark', 'theme=light', 'session_id=[Filtered]', 'preferences=[Filtered]']],
            HttpSpanDataCollector::collectCookiePairs(self::policy(), HttpMessageType::outgoingResponse(), [
                ['theme', 'dark'],
                ['theme', 'light'],
                ['session_id', 'secret'],
                ['preferences', ['theme' => 'dark']],
            ])
        );
    }

    public function testClearedCookiesHaveAnEmptyValue(): void
    {
        $this->assertSame(
            ['http.response.header.set-cookie' => ['theme=', 'session_id=[Filtered]']],
            HttpSpanDataCollector::collectCookiePairs(self::policy(), HttpMessageType::outgoingResponse(), [
                ['theme', null],
                ['session_id', null],
            ])
        );
    }

    public function testCookiesFollowTheConfiguredCollectionBehavior(): void
    {
        $this->assertSame(
            ['http.request.header.cookie' => ['theme=dark', 'lang=[Filtered]', 'session_id=[Filtered]']],
            HttpSpanDataCollector::collectCookieHeaders(self::policy(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'session_id']]]), HttpMessageType::outgoingRequest(), ['theme=dark; lang=en; session_id=secret'])
        );

        $this->assertSame([], HttpSpanDataCollector::collectCookieHeaders(self::policy(['cookies' => ['mode' => 'off']]), HttpMessageType::outgoingRequest(), ['theme=dark']));
        $this->assertSame([], HttpSpanDataCollector::collectCookiePairs(self::policy(['cookies' => ['mode' => 'off']]), HttpMessageType::outgoingResponse(), [['theme', 'dark']]));
    }

    /**
     * @param mixed                 $body
     * @param array<string, string> $expectedData
     *
     * @dataProvider bodyDataProvider
     */
    public function testBodyIsCollected($body, string $contentType, array $expectedData): void
    {
        $this->assertSame($expectedData, HttpSpanDataCollector::collectBody(self::policy(), HttpMessageType::outgoingResponse(), $body, $contentType));
    }

    public static function bodyDataProvider(): \Generator
    {
        yield 'raw bodies are parsed and encoded as JSON' => [
            '{"status":"ok","token":"secret"}',
            'application/json',
            ['http.response.body.data' => '{"status":"ok","token":"[Filtered]"}'],
        ];

        yield 'parsed bodies are encoded as JSON' => [
            ['status' => 'ok', 'token' => 'secret'],
            '',
            ['http.response.body.data' => '{"status":"ok","token":"[Filtered]"}'],
        ];

        yield 'bodies that cannot be parsed are filtered' => [
            'Hello World',
            'text/plain',
            ['http.response.body.data' => '[Filtered]'],
        ];

        yield 'invalid UTF-8 is replaced instead of filtering the whole body' => [
            ['name' => "Al\xB1ce"],
            '',
            ['http.response.body.data' => "{\"name\":\"Al\u{FFFD}ce\"}"],
        ];

        yield 'non-ASCII characters are not escaped' => [
            '{"name":"Jürgen 日本語"}',
            'application/json',
            ['http.response.body.data' => '{"name":"Jürgen 日本語"}'],
        ];

        yield 'empty bodies are not collected' => [
            '',
            'application/json',
            [],
        ];
    }

    public function testNothingIsCollectedWhenCollectionIsDisabled(): void
    {
        $policy = self::policy([
            'cookies' => ['mode' => 'off'],
            'http_headers' => ['mode' => 'off'],
            'http_bodies' => [],
        ]);

        $request = (new ServerRequest('POST', 'https://example.com/', ['Content-Type' => 'application/json'], '{"name":"Alice"}'))->withCookieParams(['theme' => 'dark']);

        $this->assertSame([], HttpSpanDataCollector::collectServerRequest($policy, $request));
    }

    public function testNothingIsCollectedWithLegacyOptions(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => true]));
        $type = HttpMessageType::incomingRequest();
        $request = (new ServerRequest('POST', 'https://example.com/', ['Content-Type' => 'application/json', 'Content-Length' => '16'], '{"name":"Alice"}'))->withCookieParams(['theme' => 'dark']);

        $this->assertSame([], HttpSpanDataCollector::collectServerRequest($policy, $request));
        $this->assertSame([], HttpSpanDataCollector::collectPsr7Request($policy, $type, $request));
        $this->assertSame([], HttpSpanDataCollector::collectPsr7Response($policy, HttpMessageType::incomingResponse(), new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}')));
        $this->assertSame([], HttpSpanDataCollector::collectHeaders($policy, $type, ['Content-Type' => ['application/json']]));
        $this->assertSame([], HttpSpanDataCollector::collectCookieHeaders($policy, $type, ['theme=dark']));
        $this->assertSame([], HttpSpanDataCollector::collectCookiePairs($policy, $type, [['theme', 'dark']]));
        $this->assertSame([], HttpSpanDataCollector::collectBody($policy, $type, ['name' => 'Alice']));
    }

    /**
     * @param array<string, mixed> $dataCollection
     */
    private static function policy(array $dataCollection = []): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));
    }
}
