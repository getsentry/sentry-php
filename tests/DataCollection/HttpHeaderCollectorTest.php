<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpHeaderCollector;
use Sentry\DataCollection\HttpMessageType;
use Sentry\Options;

final class HttpHeaderCollectorTest extends TestCase
{
    public function testDefaultHeaderRestrictions(): void
    {
        $headers = [
            'Authorization' => ['secret'],
            'Proxy-Authorization' => ['secret'],
            'Cookie' => ['theme=dark'],
            'Set-Cookie' => ['theme=dark'],
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
            'X-Authorization-Token' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ];

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'Proxy-Authorization' => ['[Filtered]'],
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
            'X-Authorization-Token' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], HttpHeaderCollector::collect($this->options([]), HttpMessageType::incomingRequest(), $headers));
    }

    public function testCollectHeadersSupportsNumericNames(): void
    {
        $this->assertSame(
            ['123' => ['test']],
            HttpHeaderCollector::collect($this->options([]), HttpMessageType::incomingRequest(), [123 => ['test']])
        );
    }

    public function testRequestAndResponseHeadersUseTheirOwnBehavior(): void
    {
        $options = $this->options([
            'http_headers' => [
                'request' => [
                    'mode' => 'allowList',
                    'terms' => ['x-request-id'],
                ],
                'response' => ['mode' => 'off'],
            ],
        ]);
        $headers = [
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
            'Host' => ['example.com'],
        ];
        $expected = [
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ];

        $this->assertSame($expected, HttpHeaderCollector::collect($options, HttpMessageType::incomingRequest(), $headers));
        $this->assertSame($expected, HttpHeaderCollector::collect($options, HttpMessageType::outgoingRequest(), $headers));
        $this->assertNull(HttpHeaderCollector::collect($options, HttpMessageType::incomingResponse(), $headers));
        $this->assertNull(HttpHeaderCollector::collect($options, HttpMessageType::outgoingResponse(), $headers));
    }

    public function testCookieHeadersAreExcludedEvenWhenExplicitlyAllowed(): void
    {
        $options = $this->options([
            'cookies' => ['mode' => 'off'],
            'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie', 'set-cookie', 'x-test']],
        ]);

        $this->assertSame(['X-Test' => ['visible']], HttpHeaderCollector::collect($options, HttpMessageType::incomingRequest(), [
            'CoOkIe' => ['theme=dark'],
            'SET-COOKIE' => ['malformed'],
            'X-Test' => ['visible'],
        ]));
    }

    public function testHeadersAreNotCollectedWhenDisabled(): void
    {
        $options = $this->options(['http_headers' => ['mode' => 'off']]);

        $this->assertNull(HttpHeaderCollector::collect($options, HttpMessageType::incomingRequest(), ['Cookie' => ['theme=dark']]));
    }

    /**
     * @dataProvider refererHeaderDataProvider
     *
     * @param array<string, mixed>    $dataCollection
     * @param array<string, string[]> $headers
     * @param array<string, string[]> $expectedHeaders
     */
    public function testRefererQueryStringFollowsTheQueryParamCollectionBehavior(array $dataCollection, array $headers, array $expectedHeaders): void
    {
        $this->assertSame($expectedHeaders, HttpHeaderCollector::collect($this->options($dataCollection), HttpMessageType::incomingRequest(), $headers));
    }

    public static function refererHeaderDataProvider(): \Generator
    {
        yield 'sensitive query params are filtered' => [
            [],
            ['ReFeReR' => ['https://example.com/search?token=secret&q=a%20b%26c#results']],
            ['ReFeReR' => ['https://example.com/search?token=[Filtered]&q=a%20b%26c#results']],
        ];

        yield 'configured query param terms are applied' => [
            ['url_query_params' => ['mode' => 'allowList', 'terms' => ['q']]],
            ['Referer' => ['https://example.com/search?q=php&page=2']],
            ['Referer' => ['https://example.com/search?q=php&page=[Filtered]']],
        ];

        yield 'query string is removed when query params are not collected' => [
            ['url_query_params' => ['mode' => 'off']],
            ['Referer' => ['https://example.com/search?q=php']],
            ['Referer' => ['https://example.com/search']],
        ];

        yield 'credentials are filtered' => [
            [],
            ['Referer' => ['https://user:pass@example.com/']],
            ['Referer' => ['https://[Filtered]:[Filtered]@example.com/']],
        ];

        yield 'unparseable URL is filtered' => [
            [],
            ['Referer' => ['http://:80']],
            ['Referer' => ['[Filtered]']],
        ];

        yield 'header filtered by the header collection behavior stays filtered' => [
            ['http_headers' => ['mode' => 'allowList', 'terms' => ['x-request-id']]],
            ['Referer' => ['https://example.com/search?q=php']],
            ['Referer' => ['[Filtered]']],
        ];

        yield 'other URL headers are not changed' => [
            [],
            ['X-Referer' => ['https://example.com/search?token=secret']],
            ['X-Referer' => ['https://example.com/search?token=secret']],
        ];
    }

    public function testUnavailableHeadersAreNotCollected(): void
    {
        $this->assertNull(HttpHeaderCollector::collect($this->options([]), HttpMessageType::incomingRequest(), null));
    }

    /**
     * @param array<string, mixed> $dataCollection
     */
    private function options(array $dataCollection): Options
    {
        return new Options(['data_collection' => $dataCollection]);
    }
}
