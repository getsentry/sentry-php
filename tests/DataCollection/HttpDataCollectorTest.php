<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\Tracing\Span;

final class HttpDataCollectorTest extends TestCase
{
    public function testCollectQueryStringPreservesLegacyBehavior(): void
    {
        $this->assertSame('token=secret&q=a%20b', HttpDataCollector::collectQueryString(null, 'token=secret&q=a%20b'));
        $this->assertNull(HttpDataCollector::collectQueryString(null, ''));
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

    public function testCollectHeadersFiltersSensitiveHeadersAndCookies(): void
    {
        $this->assertSame([
            'http.request.header.cookie' => ['[Filtered]', '[Filtered]'],
            'http.request.header.authorization' => ['[Filtered]'],
            'http.request.header.x-request-id' => ['request-id'],
        ], HttpDataCollector::collectHeaders(new DataCollectionOptions(), [
            'Authorization' => ['Bearer secret'],
            'X-Request-ID' => ['request-id'],
            'Cookie' => ['session_id=secret', 'theme=dark'],
        ], 'request'));
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
        $this->assertSame([], HttpDataCollector::collectHeaders($options, $headers, 'request'));
        $this->assertSame([
            'http.response.header.x-test' => ['plain'],
            'http.response.header.x-other' => ['[Filtered]'],
            'http.response.header.authorization' => ['[Filtered]'],
        ], HttpDataCollector::collectHeaders($options, $headers, 'response'));
        $options = new DataCollectionOptions(['http_headers' => ['mode' => 'off']]);
        $this->assertSame(['http.response.header.set-cookie' => ['[Filtered]']], HttpDataCollector::collectHeaders($options, $headers, 'response'));
        $this->assertSame([], HttpDataCollector::collectHeaders($options, ['cookie' => []], 'request'));
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
