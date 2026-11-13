<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpUrlCollector;
use Sentry\Options;

final class HttpUrlCollectorTest extends TestCase
{
    public function testUrlRetainsRawQueryNamesEncodingAndRepeatedParameters(): void
    {
        $options = new Options();
        $url = 'https://user:password@example.com/a?tag[]=one&tag[]=two&q=a+b&q=a%20b&%74oken=secret&flag#fragment';

        $this->assertSame(
            'https://[Filtered]:[Filtered]@example.com/a?tag[]=one&tag[]=two&q=a+b&q=a%20b&%74oken=[Filtered]&flag#fragment',
            HttpUrlCollector::collect($options, HttpMessageType::incomingRequest(), $url)
        );
    }

    public function testCollectionObservesOptionChanges(): void
    {
        $options = new Options();
        $url = 'https://user:password@example.com/?token=secret&q=a+b#fragment';

        $this->assertSame('https://[Filtered]:[Filtered]@example.com/?token=[Filtered]&q=a+b#fragment', HttpUrlCollector::collect($options, HttpMessageType::incomingRequest(), $url));

        $options->setDataCollection(['url_query_params' => ['mode' => 'off']]);

        $this->assertSame('https://[Filtered]:[Filtered]@example.com/#fragment', HttpUrlCollector::collect($options, HttpMessageType::incomingRequest(), $url));
        $this->assertNull(HttpUrlCollector::collectQueryString($options, 'q=a+b'));
    }

    public function testCollectQueryStringPreservesEncoding(): void
    {
        $this->assertSame(
            'api%5Ftoken=[Filtered]&q=a%20b%26c',
            HttpUrlCollector::collectQueryString($this->options([]), 'api%5Ftoken=secret&q=a%20b%26c')
        );
    }

    public function testEmptyAndDisabledQueryStringsAreNotCollected(): void
    {
        $this->assertNull(HttpUrlCollector::collectQueryString($this->options([]), ''));
        $this->assertNull(HttpUrlCollector::collectQueryString($this->options(['url_query_params' => ['mode' => 'off']]), 'token=secret'));
    }

    /**
     * @dataProvider urlDataProvider
     */
    public function testCollectUrl(string $url, string $expected): void
    {
        $this->assertSame($expected, HttpUrlCollector::collect($this->options([]), HttpMessageType::incomingRequest(), $url));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public function urlDataProvider(): \Generator
    {
        yield 'replace credentials without a query' => ['https://user:password@example.com/a', 'https://[Filtered]:[Filtered]@example.com/a'];
        yield 'replace a user name without a password' => ['https://user@example.com:8080/a', 'https://[Filtered]@example.com:8080/a'];
        yield 'keep URLs without credentials' => ['https://example.com:8080/a#fragment', 'https://example.com:8080/a#fragment'];
        yield 'keep the fragment as it appears' => ['https://example.com/a#tab[]=one', 'https://example.com/a#tab[]=one'];
        yield 'relative URL' => ['/a?token=secret', '/a?token=[Filtered]'];
        yield 'signed URL' => ['https://example.com/a?expires=1767225600&signature=secret', 'https://example.com/a?expires=1767225600&signature=[Filtered]'];
        yield 'query string that is falsy' => ['https://example.com/?0', 'https://example.com/?0'];
        yield 'empty query string' => ['https://example.com/?', 'https://example.com/'];
    }

    public function testUriInstancesAreCollected(): void
    {
        $uri = new Uri('https://user:password@example.com/a?token=secret&q=1#fragment');

        $this->assertSame('https://[Filtered]:[Filtered]@example.com/a?token=[Filtered]&q=1#fragment', HttpUrlCollector::collect($this->options([]), HttpMessageType::incomingRequest(), $uri));
        $this->assertSame('https://[Filtered]:[Filtered]@example.com/a#fragment', HttpUrlCollector::collect($this->options(['url_query_params' => ['mode' => 'off']]), HttpMessageType::incomingRequest(), $uri));
    }

    public function testUrlsThatCannotBeParsedAreNotCollected(): void
    {
        $this->assertNull(HttpUrlCollector::collect($this->options([]), HttpMessageType::incomingRequest(), 'http://exa mple.com:99999/'));
    }

    /**
     * @param array<string, mixed> $dataCollection
     */
    private function options(array $dataCollection): Options
    {
        return new Options(['data_collection' => $dataCollection]);
    }
}
