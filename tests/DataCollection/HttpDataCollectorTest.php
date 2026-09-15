<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\Options;

final class HttpDataCollectorTest extends TestCase
{
    public function testPsr7BodyDataIsCollectedWithoutChangingTheStreamPosition(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"name":"Alice","token":"secret"}'
        );
        $response->getBody()->seek(4);

        $this->assertSame([
            'http.response.body.data' => ['name' => 'Alice', 'token' => '[Filtered]'],
        ], HttpDataCollector::collectPsr7BodyData(
            $this->policy([]),
            DataCollectionOptions::HTTP_BODY_INCOMING_RESPONSE,
            $response
        ));
        $this->assertSame(4, $response->getBody()->tell());
    }

    public function testDisabledPsr7BodyDataIsNotRead(): void
    {
        $response = new Response(200, [], 'response body');
        $response->getBody()->seek(4);

        $this->assertSame([], HttpDataCollector::collectPsr7BodyData(
            $this->policy(['http_bodies' => []]),
            DataCollectionOptions::HTTP_BODY_INCOMING_RESPONSE,
            $response
        ));
        $this->assertSame(4, $response->getBody()->tell());
    }

    public function testPsr7RequestAndResponseDataAreCollected(): void
    {
        $policy = $this->policy([]);
        $request = new Request('GET', '/', [
            'X-Request-Id' => 'request-id',
            'Cookie' => 'theme=dark',
        ]);
        $response = new Response(200, [
            'X-Response-Id' => 'response-id',
            'Set-Cookie' => 'theme=light',
        ]);

        $this->assertSame([
            'http.request.header.x-request-id' => ['request-id'],
            'http.request.header.cookie.theme' => 'dark',
        ], HttpDataCollector::collectPsr7RequestData($policy, $request));
        $this->assertSame([
            'http.response.header.x-response-id' => ['response-id'],
            'http.response.header.set_cookie.theme' => 'light',
        ], HttpDataCollector::collectPsr7ResponseData($policy, $response));
    }

    public function testDisabledPsr7HeaderAndCookieDataAreNotAcquired(): void
    {
        $policy = $this->policy([
            'cookies' => ['mode' => 'off'],
            'http_headers' => ['mode' => 'off'],
        ]);
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->never())->method('getHeaders');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->never())->method('getHeaders');

        $this->assertSame([], HttpDataCollector::collectPsr7RequestData($policy, $request));
        $this->assertSame([], HttpDataCollector::collectPsr7ResponseData($policy, $response));
    }

    public function testRequestDataCollectsHeadersAndCookies(): void
    {
        $data = HttpDataCollector::collectRequestData($this->policy([]), [
            'authorization' => ['secret'], 'cookie' => ['theme=dark; session_id=secret'],
        ]);
        $this->assertSame([
            'http.request.header.authorization' => ['[Filtered]'],
            'http.request.header.cookie.theme' => 'dark',
            'http.request.header.cookie.session_id' => '[Filtered]',
        ], $data);
        $this->assertSame([], HttpDataCollector::collectRequestData($this->policy(null), []));
    }

    public function testCollectQueryStringPreservesLegacyBehavior(): void
    {
        $policy = $this->policy(null);

        $this->assertSame('token=secret&q=a%20b', HttpDataCollector::collectQueryString($policy, 'token=secret&q=a%20b'));
        $this->assertNull(HttpDataCollector::collectQueryString($policy, ''));
    }

    public function testConfiguredDefaultsCollectParsedCookiesAndHeaders(): void
    {
        [$request, $response] = $this->collectParsedCookies($this->policy([]));

        $this->assertSame([
            'http.request.header.x-test' => ['visible'],
            'http.request.header.cookie.theme' => 'parsed',
            'http.request.header.cookie.session_id' => '[Filtered]',
        ], $request);
        $this->assertSame([
            'http.response.header.x-test' => ['visible'],
            'http.response.header.set_cookie.theme' => ['first', 'second'],
            'http.response.header.set_cookie.locale' => null,
            'http.response.header.set_cookie.session_id' => '[Filtered]',
        ], $response);
    }

    public function testParsedCookiesAreCollectedWhenHeadersAreDisabled(): void
    {
        [$request, $response] = $this->collectParsedCookies($this->policy(['http_headers' => ['mode' => 'off']]));

        $this->assertSame([
            'http.request.header.cookie.theme' => 'parsed',
            'http.request.header.cookie.session_id' => '[Filtered]',
        ], $request);
        $this->assertSame([
            'http.response.header.set_cookie.theme' => ['first', 'second'],
            'http.response.header.set_cookie.locale' => null,
            'http.response.header.set_cookie.session_id' => '[Filtered]',
        ], $response);
    }

    public function testHeadersAreCollectedWhenCookiesAreDisabled(): void
    {
        [$request, $response] = $this->collectParsedCookies($this->policy(['cookies' => ['mode' => 'off']]));

        $this->assertSame(['http.request.header.x-test' => ['visible']], $request);
        $this->assertSame(['http.response.header.x-test' => ['visible']], $response);
    }

    public function testLegacyModeDoesNotCollectParsedCookiesOrSpanHeaders(): void
    {
        [$request, $response] = $this->collectParsedCookies($this->policy(null));

        $this->assertSame([], $request);
        $this->assertSame([], $response);
    }

    public function testEmptyParsedCookiesDoNotFallBackToRawHeaders(): void
    {
        $policy = $this->policy([]);
        $this->assertSame([], HttpDataCollector::collectRequestData($policy, ['cookie' => ['theme=raw']], []));
        $this->assertSame([], HttpDataCollector::collectResponseData($policy, ['set-cookie' => ['theme=raw']], []));
        $this->assertSame(['http.request.header.cookie.theme' => 'raw'], HttpDataCollector::collectRequestData($policy, ['cookie' => ['theme=raw']]));
        $this->assertSame(['http.response.header.set_cookie.theme' => 'raw'], HttpDataCollector::collectResponseData($policy, ['set-cookie' => ['theme=raw']]));
    }

    public function testUrlSelectsLegacyInputOnlyWithoutDataCollection(): void
    {
        $declared = 'https://example.com/?tag=a&tag=b&%74oken=secret&q=a+b';
        $legacy = 'https://example.com/?q=a%20b&tag=b&token=secret';
        $this->assertSame($legacy, HttpDataCollector::collectUrl($this->policy(null), $declared, $legacy));
        $this->assertSame('https://example.com/?tag=a&tag=b&%74oken=[Filtered]&q=a+b', HttpDataCollector::collectUrl($this->policy([]), $declared, $legacy));
        $this->assertSame('https://example.com/', HttpDataCollector::collectUrl($this->policy(['url_query_params' => ['mode' => 'off']]), $declared, $legacy));
    }

    public function testRemovedHeadersAreNotCollected(): void
    {
        $options = new DataCollectionOptions();
        $this->assertSame([], HttpDataCollector::collectRequestHeaders($options, ['x-removed' => []]));
        $this->assertSame([], HttpDataCollector::collectResponseHeaders($options, ['x-removed' => []]));
    }

    public function testResponseDataCollectsHeadersWithoutBodyData(): void
    {
        $headers = ['content-type' => ['application/problem+json']];
        $this->assertSame([
            'http.response.header.content-type' => ['application/problem+json'],
        ], HttpDataCollector::collectResponseData($this->policy([]), $headers));
        $this->assertSame([], HttpDataCollector::collectResponseData($this->policy(null), $headers));
    }

    public function testCookiePairsPreserveDuplicateAndNullValues(): void
    {
        $this->assertSame(['theme' => [null, 'light', 'dark'], 'language' => 'en'], HttpDataCollector::groupCookieValues([
            ['theme', null], ['theme', 'light'], ['language', 'en'], ['theme', 'dark'],
        ]));
    }

    public function testQueryDataPreservesEncodingAndOmitsUncollectedQueries(): void
    {
        $this->assertSame([], HttpDataCollector::collectQueryData($this->policy([]), ''));
        $this->assertSame([], HttpDataCollector::collectQueryData($this->policy(['url_query_params' => ['mode' => 'off']]), 'token=secret'));
        $this->assertSame(['http.query' => 'token=secret'], HttpDataCollector::collectQueryData($this->policy(null), 'token=secret'));
        $this->assertSame(
            ['http.query' => '%74oken=[Filtered]&page=new&q=a+b'],
            HttpDataCollector::collectQueryData(
                $this->policy([]),
                '%74oken=secret&page=new&q=a+b'
            )
        );
    }

    public function testCollectQueryStringPreservesEncoding(): void
    {
        $this->assertSame(
            'api%5Ftoken=[Filtered]&q=a%20b%26c',
            HttpDataCollector::collectQueryString($this->policy([]), 'api%5Ftoken=secret&q=a%20b%26c')
        );
    }

    public function testEmptyAndDisabledQueryStringsAreNotCollected(): void
    {
        $this->assertNull(HttpDataCollector::collectQueryString($this->policy([]), ''));
        $this->assertNull(HttpDataCollector::collectQueryString($this->policy(['url_query_params' => ['mode' => 'off']]), 'token=secret'));
    }

    /**
     * @dataProvider urlDataProvider
     *
     * @param array<string, mixed>|null $options
     */
    public function testCollectUrl(?array $options, string $url, string $expected): void
    {
        $this->assertSame($expected, HttpDataCollector::collectUrl($this->policy($options), $url));
    }

    /**
     * @return \Generator<string, array{array<string, mixed>|null, string, string}>
     */
    public function urlDataProvider(): \Generator
    {
        $url = 'https://user:password@example.com/a?z=a%20b%26c&api%5Ftoken=secret&z=x+y#fragment';
        yield 'legacy URL is unchanged' => [null, $url, $url];
        yield 'filter without re-encoding' => [[], $url, 'https://example.com/a?z=a%20b%26c&api%5Ftoken=[Filtered]&z=x+y'];
        yield 'query collection disabled' => [['url_query_params' => ['mode' => 'off']], $url, 'https://example.com/a'];
        yield 'remove credentials without a query' => [[], 'https://user:password@example.com/a', 'https://example.com/a'];
        yield 'relative URL' => [[], '/a?token=secret', '/a?token=[Filtered]'];
    }

    public function testCollectHeadersFiltersSensitiveHeadersAndExcludesCookies(): void
    {
        $this->assertSame([
            'http.request.header.authorization' => ['[Filtered]'],
            'http.request.header.x-request-id' => ['request-id'],
        ], HttpDataCollector::collectRequestHeaders(new DataCollectionOptions(), [
            'authorization' => ['Bearer secret'],
            'x-request-id' => ['request-id'],
            'cookie' => ['session_id=secret', 'theme=dark'],
            'set-cookie' => ['theme=light'],
        ]));
    }

    public function testHeaderDirectionsAndCookiesAreIndependent(): void
    {
        $options = new DataCollectionOptions([
            'cookies' => ['mode' => 'off'],
            'http_headers' => [
                'request' => ['mode' => 'off'],
                'response' => ['mode' => 'allowList', 'terms' => ['x-test', 'authorization']],
            ],
        ]);
        $headers = ['x-test' => ['plain'], 'x-other' => ['other'], 'authorization' => ['secret'], 'set-cookie' => ['theme=dark']];
        $this->assertSame([], HttpDataCollector::collectRequestHeaders($options, $headers));
        $this->assertSame([
            'http.response.header.x-test' => ['plain'],
            'http.response.header.x-other' => ['[Filtered]'],
            'http.response.header.authorization' => ['[Filtered]'],
        ], HttpDataCollector::collectResponseHeaders($options, $headers));
        $options = new DataCollectionOptions(['http_headers' => ['mode' => 'off']]);
        $this->assertSame([], HttpDataCollector::collectResponseHeaders($options, $headers));
    }

    /**
     * @dataProvider headerDirectionAndCookieModeProvider
     */
    public function testHeadersCannotOptIntoCookieHeaders(string $method, string $cookieMode): void
    {
        $options = new DataCollectionOptions([
            'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie']],
            'cookies' => ['mode' => $cookieMode, 'terms' => ['theme']],
        ]);

        $this->assertSame([], HttpDataCollector::$method($options, [
            'cookie' => ['theme=dark'],
            'set-cookie' => ['theme=light'],
        ]));
    }

    public function headerDirectionAndCookieModeProvider(): \Generator
    {
        yield 'request with cookies off' => ['collectRequestHeaders', 'off'];
        yield 'request with cookie deny list' => ['collectRequestHeaders', 'denyList'];
        yield 'request with cookie allow list' => ['collectRequestHeaders', 'allowList'];
        yield 'response with cookies off' => ['collectResponseHeaders', 'off'];
        yield 'response with cookie deny list' => ['collectResponseHeaders', 'denyList'];
        yield 'response with cookie allow list' => ['collectResponseHeaders', 'allowList'];
    }

    /**
     * @dataProvider parsedCookieBehaviorProvider
     *
     * @param array<string, mixed> $behavior
     * @param array<string, mixed> $expected
     */
    public function testParsedCookiesAreIndependentOfHeaders(string $method, array $behavior, array $expected): void
    {
        $options = new DataCollectionOptions(['cookies' => $behavior, 'http_headers' => ['mode' => 'off']]);

        $this->assertSame($expected, HttpDataCollector::$method($options, ['theme' => 'dark', 'SESSION_id' => 'secret']));
    }

    public function parsedCookieBehaviorProvider(): \Generator
    {
        foreach (['collectRequestCookies' => 'http.request.header.cookie.', 'collectResponseCookies' => 'http.response.header.set_cookie.'] as $method => $prefix) {
            yield $method . ' deny list' => [$method, ['mode' => 'denyList'], [
                $prefix . 'theme' => 'dark',
                $prefix . 'SESSION_id' => '[Filtered]',
            ]];
            yield $method . ' allow list' => [$method, ['mode' => 'allowList', 'terms' => ['theme', 'session']], [
                $prefix . 'theme' => 'dark',
                $prefix . 'SESSION_id' => '[Filtered]',
            ]];
            yield $method . ' off' => [$method, ['mode' => 'off'], []];
            yield $method . ' custom deny list' => [$method, ['mode' => 'denyList', 'terms' => ['theme']], [
                $prefix . 'theme' => '[Filtered]',
                $prefix . 'SESSION_id' => '[Filtered]',
            ]];
        }
    }

    public function testMalformedRequestCookieHeaderUsesFilteredFallback(): void
    {
        $this->assertSame(
            ['http.request.header.cookie' => '[Filtered]'],
            HttpDataCollector::collectRequestData($this->policy([]), ['cookie' => ['malformed']])
        );
    }

    public function testMalformedResponseCookieHeaderUsesFilteredFallback(): void
    {
        $this->assertSame(
            ['http.response.header.set_cookie' => '[Filtered]'],
            HttpDataCollector::collectResponseData($this->policy([]), ['set-cookie' => ['malformed']])
        );
    }

    public function testMalformedRequestCookieFallbackIsRetainedWithParsedCookies(): void
    {
        $this->assertSame(
            ['http.request.header.cookie.theme' => 'parsed', 'http.request.header.cookie' => '[Filtered]'],
            HttpDataCollector::collectRequestData($this->policy([]), ['cookie' => ['malformed']], ['theme' => 'parsed'])
        );
    }

    public function testMalformedResponseCookieFallbackIsRetainedWithParsedCookies(): void
    {
        $this->assertSame(
            ['http.response.header.set_cookie.theme' => 'parsed', 'http.response.header.set_cookie' => '[Filtered]'],
            HttpDataCollector::collectResponseData($this->policy([]), ['set-cookie' => ['malformed']], [['theme', 'parsed']])
        );
    }

    public function testRequestCookieHeaderParsing(): void
    {
        $this->assertSame(['theme' => ['dark', 'light'], 'session' => 'a=b', 'empty' => ''], HttpDataCollector::parseRequestCookies([
            'theme=dark; session=a=b; empty=; malformed; =ignored', 'theme=light',
        ]));
    }

    public function testResponseCookieHeaderParsing(): void
    {
        $this->assertSame(['theme' => ['dark', 'light'], 'session' => 'a=b'], HttpDataCollector::parseResponseCookies([
            'theme=dark; Path=/; Expires=Wed, 09 Jun 2027 10:18:14 GMT',
            'theme=light; Path=/other; Secure',
            'session=a=b; HttpOnly', 'malformed',
        ]));
    }

    /**
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private function collectParsedCookies(DataCollectionPolicy $policy): array
    {
        $headers = ['x-test' => ['visible'], 'cookie' => ['theme=raw'], 'set-cookie' => ['theme=raw']];

        return [
            HttpDataCollector::collectRequestData($policy, $headers, ['theme' => 'parsed', 'session_id' => 'secret']),
            HttpDataCollector::collectResponseData($policy, $headers, [
                ['theme', 'first'], ['theme', 'second'], ['locale', null], ['session_id', 'secret'],
            ]),
        ];
    }

    /**
     * @param array<string, mixed>|null $dataCollection
     */
    private function policy(?array $dataCollection): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));
    }
}
