<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpUrlCollector;
use Sentry\Options;

final class HttpUrlCollectorTest extends TestCase
{
    public function testUrlRetainsRawQueryNamesEncodingAndRepeatedParameters(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['data_collection' => []]));
        $url = 'https://user:password@example.com/a?tag[]=one&tag[]=two&q=a+b&q=a%20b&%74oken=secret&flag#fragment';

        $this->assertSame(
            'https://[Filtered]:[Filtered]@example.com/a?tag[]=one&tag[]=two&q=a+b&q=a%20b&%74oken=[Filtered]&flag#fragment',
            HttpUrlCollector::collect($policy, HttpMessageType::incomingRequest(), $url)
        );
    }

    public function testCollectionObservesPolicyChanges(): void
    {
        $options = new Options();
        $policy = DataCollectionPolicy::fromOptions($options);
        $url = 'https://user:password@example.com/?token=secret&q=a+b#fragment';

        $this->assertSame($url, HttpUrlCollector::collect($policy, HttpMessageType::incomingRequest(), $url));

        $options->updateOptions(['data_collection' => []]);

        $this->assertSame('https://[Filtered]:[Filtered]@example.com/?token=[Filtered]&q=a+b#fragment', HttpUrlCollector::collect($policy, HttpMessageType::incomingRequest(), $url));

        $options->updateOptions(['data_collection' => ['url_query_params' => ['mode' => 'off']]]);

        $this->assertSame('https://[Filtered]:[Filtered]@example.com/#fragment', HttpUrlCollector::collect($policy, HttpMessageType::incomingRequest(), $url));
        $this->assertNull(HttpUrlCollector::collectQueryString($policy, 'q=a+b'));

        $options->updateOptions(['data_collection' => null]);

        $this->assertSame($url, HttpUrlCollector::collect($policy, HttpMessageType::incomingRequest(), $url));
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
        $this->assertSame($expected, HttpUrlCollector::collect($this->policy([]), HttpMessageType::incomingRequest(), $url));
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
        yield 'query string that is falsy' => ['https://example.com/?0', 'https://example.com/?0'];
        yield 'empty query string' => ['https://example.com/?', 'https://example.com/'];
    }

    public function testUriInstancesAreCollected(): void
    {
        $uri = new Uri('https://user:password@example.com/a?token=secret&q=1#fragment');

        $this->assertSame('https://[Filtered]:[Filtered]@example.com/a?token=[Filtered]&q=1#fragment', HttpUrlCollector::collect($this->policy([]), HttpMessageType::incomingRequest(), $uri));
        $this->assertSame('https://[Filtered]:[Filtered]@example.com/a#fragment', HttpUrlCollector::collect($this->policy(['url_query_params' => ['mode' => 'off']]), HttpMessageType::incomingRequest(), $uri));
    }

    public function testLegacyModeReturnsUriInstancesUnchanged(): void
    {
        $url = 'https://user:password@example.com/a?token=secret#fragment';

        $this->assertSame($url, HttpUrlCollector::collect($this->policy(null), HttpMessageType::incomingRequest(), new Uri($url)));
    }

    /**
     * @dataProvider legacyUncollectedTypeProvider
     */
    public function testLegacyModeOnlyCollectsTheUrlOfIncomingRequests(HttpMessageType $type): void
    {
        $this->assertNull(HttpUrlCollector::collect($this->policy(null), $type, 'https://example.com/a?token=secret'));
    }

    public static function legacyUncollectedTypeProvider(): \Generator
    {
        yield 'outgoing request' => [HttpMessageType::outgoingRequest()];
        yield 'incoming response' => [HttpMessageType::incomingResponse()];
        yield 'outgoing response' => [HttpMessageType::outgoingResponse()];
    }

    public function testUrlsThatCannotBeParsedAreNotCollected(): void
    {
        $this->assertNull(HttpUrlCollector::collect($this->policy([]), HttpMessageType::incomingRequest(), 'http://exa mple.com:99999/'));
    }

    /**
     * @param array<string, mixed>|null $dataCollection
     */
    private function policy(?array $dataCollection): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));
    }
}
