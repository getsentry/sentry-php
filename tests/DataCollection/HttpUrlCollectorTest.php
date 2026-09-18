<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpUrlCollector;
use Sentry\Options;

final class HttpUrlCollectorTest extends TestCase
{
    public function testUrlRetainsRawQueryNamesEncodingAndRepeatedParameters(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['data_collection' => []]));
        $url = 'https://user:password@example.com/a?tag[]=one&tag[]=two&q=a+b&q=a%20b&%74oken=secret&flag#fragment';

        $this->assertSame(
            'https://example.com/a?tag[]=one&tag[]=two&q=a+b&q=a%20b&%74oken=[Filtered]&flag',
            HttpUrlCollector::collect($policy, $url)
        );
    }

    public function testCollectionObservesPolicyChanges(): void
    {
        $options = new Options();
        $policy = DataCollectionPolicy::fromOptions($options);
        $url = 'https://user:password@example.com/?token=secret&q=a+b#fragment';

        $this->assertSame($url, HttpUrlCollector::collect($policy, $url));

        $options->updateOptions(['data_collection' => []]);

        $this->assertSame('https://example.com/?token=[Filtered]&q=a+b', HttpUrlCollector::collect($policy, $url));

        $options->updateOptions(['data_collection' => ['url_query_params' => ['mode' => 'off']]]);

        $this->assertSame('https://example.com/', HttpUrlCollector::collect($policy, $url));
        $this->assertNull(HttpUrlCollector::collectQueryString($policy, 'q=a+b'));

        $options->updateOptions(['data_collection' => null]);

        $this->assertSame($url, HttpUrlCollector::collect($policy, $url));
        $this->assertSame('q=a+b', HttpUrlCollector::collectQueryString($policy, 'q=a+b'));
    }

    public function testCollectQueryStringPreservesLegacyBehavior(): void
    {
        $policy = $this->policy(null);

        $this->assertSame('token=secret&q=a%20b', HttpUrlCollector::collectQueryString($policy, 'token=secret&q=a%20b'));
        $this->assertNull(HttpUrlCollector::collectQueryString($policy, ''));
    }

    public function testCollectQueryStringPreservesEncoding(): void
    {
        $this->assertSame(
            'api%5Ftoken=[Filtered]&q=a%20b%26c',
            HttpUrlCollector::collectQueryString($this->policy([]), 'api%5Ftoken=secret&q=a%20b%26c')
        );
    }

    public function testEmptyAndDisabledQueryStringsAreNotCollected(): void
    {
        $this->assertNull(HttpUrlCollector::collectQueryString($this->policy([]), ''));
        $this->assertNull(HttpUrlCollector::collectQueryString($this->policy(['url_query_params' => ['mode' => 'off']]), 'token=secret'));
    }

    /**
     * @dataProvider urlDataProvider
     */
    public function testCollectUrl(string $url, string $expected): void
    {
        $this->assertSame($expected, HttpUrlCollector::collect($this->policy([]), $url));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public function urlDataProvider(): \Generator
    {
        yield 'remove credentials without a query' => ['https://user:password@example.com/a', 'https://example.com/a'];
        yield 'relative URL' => ['/a?token=secret', '/a?token=[Filtered]'];
    }

    /**
     * @param array<string, mixed>|null $dataCollection
     */
    private function policy(?array $dataCollection): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));
    }
}
