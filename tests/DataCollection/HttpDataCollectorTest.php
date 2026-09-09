<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\Tracing\Span;

final class HttpDataCollectorTest extends TestCase
{
    public function testRequestDataCollectsHeadersAndCookies(): void
    {
        $data = HttpDataCollector::collectRequestData(new DataCollectionOptions(), [
            'authorization' => ['secret'], 'cookie' => ['theme=dark; session_id=secret'],
        ]);
        $this->assertSame([
            'http.request.header.authorization' => ['[Filtered]'],
            'http.request.header.cookie.theme' => 'dark',
            'http.request.header.cookie.session_id' => '[Filtered]',
        ], $data);
        $this->assertSame([], HttpDataCollector::collectRequestData(null, []));
    }

    public function testCollectQueryStringPreservesLegacyBehavior(): void
    {
        $this->assertSame('token=secret&q=a%20b', HttpDataCollector::collectQueryString(null, 'token=secret&q=a%20b'));
        $this->assertNull(HttpDataCollector::collectQueryString(null, ''));
    }

    public function testParsedCookiesAreCollectedIndependentlyOfHeaders(): void
    {
        $headers = ['x-test' => ['visible'], 'cookie' => ['theme=raw'], 'set-cookie' => ['theme=raw']];
        foreach ([null, [], ['http_headers' => ['mode' => 'off']], ['cookies' => ['mode' => 'off']]] as $configuration) {
            $options = $configuration === null ? null : new DataCollectionOptions($configuration);
            $request = HttpDataCollector::collectRequestData($options, $headers, ['theme' => 'parsed', 'session_id' => 'secret']);
            $response = HttpDataCollector::collectResponseData($options, $headers, [
                ['theme', 'first'], ['theme', 'second'], ['locale', null], ['session_id', 'secret'],
            ]);
            foreach (['request' => $request, 'response' => $response] as $direction => $data) {
                if ($configuration !== null && ($configuration['http_headers']['mode'] ?? null) !== 'off') {
                    $this->assertSame(['visible'], $data['http.' . $direction . '.header.x-test']);
                } else {
                    $this->assertArrayNotHasKey('http.' . $direction . '.header.x-test', $data);
                }
                $cookiePrefix = 'http.' . $direction . '.header.' . ($direction === 'request' ? 'cookie.' : 'set_cookie.');
                if ($configuration !== null && ($configuration['cookies']['mode'] ?? null) !== 'off') {
                    $this->assertSame($direction === 'request' ? 'parsed' : ['first', 'second'], $data[$cookiePrefix . 'theme']);
                    $this->assertSame('[Filtered]', $data[$cookiePrefix . 'session_id']);
                } else {
                    $this->assertArrayNotHasKey($cookiePrefix . 'theme', $data);
                    $this->assertArrayNotHasKey($cookiePrefix . 'session_id', $data);
                }
                $this->assertArrayNotHasKey('http.' . $direction . '.header.cookie', $data);
                $this->assertArrayNotHasKey('http.' . $direction . '.header.set-cookie', $data);
            }
        }
    }

    public function testEmptyParsedCookiesDoNotFallBackToRawHeaders(): void
    {
        $options = new DataCollectionOptions();
        $this->assertSame([], HttpDataCollector::collectRequestData($options, ['cookie' => ['theme=raw']], []));
        $this->assertSame([], HttpDataCollector::collectResponseData($options, ['set-cookie' => ['theme=raw']], []));
        $this->assertSame(['http.request.header.cookie.theme' => 'raw'], HttpDataCollector::collectRequestData($options, ['cookie' => ['theme=raw']]));
        $this->assertSame(['http.response.header.set_cookie.theme' => 'raw'], HttpDataCollector::collectResponseData($options, ['set-cookie' => ['theme=raw']]));
    }

    public function testUrlSelectsLegacyInputOnlyWithoutDataCollection(): void
    {
        $declared = 'https://example.com/?tag=a&tag=b&%74oken=secret&q=a+b';
        $legacy = 'https://example.com/?q=a%20b&tag=b&token=secret';
        $this->assertSame($legacy, HttpDataCollector::collectUrl(null, $declared, $legacy));
        $this->assertSame('https://example.com/?tag=a&tag=b&%74oken=[Filtered]&q=a+b', HttpDataCollector::collectUrl(new DataCollectionOptions(), $declared, $legacy));
        $this->assertSame('https://example.com/', HttpDataCollector::collectUrl(new DataCollectionOptions(['url_query_params' => ['mode' => 'off']]), $declared, $legacy));
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
        ], HttpDataCollector::collectResponseData(new DataCollectionOptions(), $headers));
        $this->assertSame([], HttpDataCollector::collectResponseData(null, $headers));
    }

    public function testCookiePairsPreserveDuplicateAndNullValues(): void
    {
        $this->assertSame(['theme' => [null, 'light', 'dark'], 'language' => 'en'], HttpDataCollector::groupCookieValues([
            ['theme', null], ['theme', 'light'], ['language', 'en'], ['theme', 'dark'],
        ]));
    }

    public function testQueryDataPreservesEncodingAndOmitsUncollectedQueries(): void
    {
        $this->assertSame([], HttpDataCollector::collectQueryData(new DataCollectionOptions(), ''));
        $this->assertSame([], HttpDataCollector::collectQueryData(new DataCollectionOptions(['url_query_params' => ['mode' => 'off']]), 'token=secret'));
        $this->assertSame(['http.query' => 'token=secret'], HttpDataCollector::collectQueryData(null, 'token=secret'));
        $this->assertSame(
            ['http.query' => '%74oken=[Filtered]&page=new&q=a+b'],
            HttpDataCollector::collectQueryData(
                new DataCollectionOptions(),
                '%74oken=secret&page=new&q=a+b'
            )
        );
    }

    public function testCollectQueryStringPreservesEncoding(): void
    {
        $this->assertSame(
            'api%5Ftoken=[Filtered]&q=a%20b%26c',
            HttpDataCollector::collectQueryString(new DataCollectionOptions(), 'api%5Ftoken=secret&q=a%20b%26c')
        );
    }

    public function testEmptyAndDisabledQueryStringsAreNotCollected(): void
    {
        $this->assertNull(HttpDataCollector::collectQueryString(new DataCollectionOptions(), ''));
        $this->assertNull(HttpDataCollector::collectQueryString(new DataCollectionOptions(['url_query_params' => ['mode' => 'off']]), 'token=secret'));
    }

    /**
     * @dataProvider urlDataProvider
     *
     * @param array<string, mixed>|null $options
     */
    public function testCollectUrl(?array $options, string $url, string $expected): void
    {
        $this->assertSame($expected, HttpDataCollector::collectUrl($options === null ? null : new DataCollectionOptions($options), $url));
    }

    /**
     * @return \Generator<string, array{array<string, mixed>|null, string, string}>
     */
    public function urlDataProvider(): \Generator
    {
        $url = 'https://user:password@example.com/a?z=a%20b%26c&api%5Ftoken=secret&z=x+y#fragment';
        yield 'legacy URL is unchanged' => [null, $url, $url];
        yield 'filter without re-encoding' => [[], $url, 'https://example.com/a?z=a%20b%26c&api%5Ftoken=[Filtered]&z=x+y#fragment'];
        yield 'query collection disabled' => [['url_query_params' => ['mode' => 'off']], $url, 'https://example.com/a#fragment'];
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

    public function testHeadersCannotOptIntoCookieHeaders(): void
    {
        foreach (['collectRequestHeaders', 'collectResponseHeaders'] as $method) {
            foreach (['off', 'denyList', 'allowList'] as $mode) {
                $options = new DataCollectionOptions([
                    'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie']],
                    'cookies' => ['mode' => $mode, 'terms' => ['theme']],
                ]);
                $this->assertSame([], HttpDataCollector::$method($options, [
                    'cookie' => ['theme=dark'],
                    'set-cookie' => ['theme=light'],
                ]));
            }
        }
    }

    public function testParsedCookiesAreIndependentOfHeaders(): void
    {
        foreach (['collectRequestCookies' => 'http.request.header.cookie.', 'collectResponseCookies' => 'http.response.header.set_cookie.'] as $method => $prefix) {
            foreach ([
                ['mode' => 'denyList'],
                ['mode' => 'allowList', 'terms' => ['theme', 'session']],
            ] as $behavior) {
                $options = new DataCollectionOptions(['cookies' => $behavior, 'http_headers' => ['mode' => 'off']]);
                $this->assertSame([
                    $prefix . 'theme' => 'dark',
                    $prefix . 'SESSION_id' => '[Filtered]',
                ], HttpDataCollector::$method($options, ['theme' => 'dark', 'SESSION_id' => 'secret']));
            }
            $options = new DataCollectionOptions(['cookies' => ['mode' => 'off']]);
            $this->assertSame([], HttpDataCollector::$method($options, ['theme' => 'dark']));
            $options = new DataCollectionOptions(['cookies' => ['mode' => 'denyList', 'terms' => ['theme']]]);
            $this->assertSame([$prefix . 'theme' => '[Filtered]'], HttpDataCollector::$method($options, ['theme' => 'dark']));
        }
    }

    public function testCookieHeaderParsing(): void
    {
        $this->assertSame(['theme' => ['dark', 'light'], 'session' => 'a=b', 'empty' => ''], HttpDataCollector::parseRequestCookies([
            'theme=dark; session=a=b; empty=; malformed; =ignored', 'theme=light',
        ]));
        $this->assertSame(['theme' => ['dark', 'light'], 'session' => 'a=b'], HttpDataCollector::parseResponseCookies([
            'theme=dark; Path=/; Expires=Wed, 09 Jun 2027 10:18:14 GMT',
            'theme=light; Path=/other; Secure',
            'session=a=b; HttpOnly', 'malformed',
        ]));
    }

    public function testAutomaticSpanDataOnlyFillsMissingFields(): void
    {
        $span = new Span();
        $span->setData(['http.response.header.x-test' => ['explicit'], 'http.response.body.data' => null]);
        HttpDataCollector::setMissingSpanData($span, [
            'http.response.header.x-test' => ['automatic'],
            'http.response.body.data' => ['name' => 'automatic'],
            'http.response.header.content-type' => ['application/json'],
        ]);
        $this->assertSame([
            'http.response.header.x-test' => ['explicit'],
            'http.response.body.data' => null,
            'http.response.header.content-type' => ['application/json'],
        ], $span->getData());
    }
}
