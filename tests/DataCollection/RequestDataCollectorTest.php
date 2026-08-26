<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\RequestDataCollector;

final class RequestDataCollectorTest extends TestCase
{
    public function testUsesDataCollectionDistinguishesConfiguredAndLegacyModes(): void
    {
        $this->assertFalse($this->legacyCollector(false)->usesDataCollection());
        $this->assertFalse($this->legacyCollector(true)->usesDataCollection());
        $this->assertTrue($this->collector([])->usesDataCollection());
    }

    public function testShouldCollectUserInfoFollowsLegacySendDefaultPii(): void
    {
        $this->assertFalse($this->legacyCollector(false)->shouldCollectUserInfo());
        $this->assertTrue($this->legacyCollector(true)->shouldCollectUserInfo());
    }

    public function testShouldCollectUserInfoUsesDataCollectionWhenConfigured(): void
    {
        $enabled = new RequestDataCollector(new DataCollectionOptions(['user_info' => true]), false);
        $disabled = new RequestDataCollector(new DataCollectionOptions(['user_info' => false]), true);

        $this->assertTrue($enabled->shouldCollectUserInfo());
        $this->assertFalse($disabled->shouldCollectUserInfo());
    }

    public function testCollectQueryStringPreservesLegacyBehavior(): void
    {
        $queryString = 'api%5Ftoken=secret&q=a%20b%26c';

        $this->assertSame($queryString, $this->legacyCollector(false)->collectQueryString($queryString));
        $this->assertSame($queryString, $this->legacyCollector(true)->collectQueryString($queryString));
        $this->assertNull($this->legacyCollector(false)->collectQueryString(''));
    }

    public function testCollectQueryStringUsesUrlQueryParamsBehavior(): void
    {
        $collector = $this->collector([
            'url_query_params' => [
                'mode' => 'denyList',
                'terms' => ['page'],
            ],
        ]);

        $this->assertSame(
            'api%5Ftoken=[Filtered]&page=[Filtered]&q=a%20b%26c',
            $collector->collectQueryString('api%5Ftoken=secret&page=5&q=a%20b%26c')
        );
    }

    public function testCollectQueryStringReturnsNullWhenDisabledOrEmpty(): void
    {
        $disabled = $this->collector(['url_query_params' => ['mode' => 'off']]);

        $this->assertNull($disabled->collectQueryString('page=5'));
        $this->assertNull($this->collector([])->collectQueryString(''));
    }

    public function testCollectCookiesPreservesLegacyBehavior(): void
    {
        $cookies = ['session_id' => 'secret', 'theme' => 'dark'];

        $this->assertSame($cookies, $this->legacyCollector(true)->collectCookies($cookies));
        $this->assertNull($this->legacyCollector(false)->collectCookies($cookies));
    }

    public function testCollectCookiesUsesConfiguredBehavior(): void
    {
        $collector = $this->collector([
            'cookies' => [
                'mode' => 'allowList',
                'terms' => ['theme'],
            ],
        ]);

        $this->assertSame([
            'session_id' => '[Filtered]',
            'theme' => 'dark',
            'tracking_id' => '[Filtered]',
        ], $collector->collectCookies([
            'session_id' => 'secret',
            'theme' => 'dark',
            'tracking_id' => '12345',
        ]));
    }

    public function testCollectCookiesReturnsNullWhenDisabled(): void
    {
        $collector = $this->collector(['cookies' => ['mode' => 'off']]);

        $this->assertNull($collector->collectCookies(['theme' => 'dark']));
    }

    public function testCollectHeadersPreservesLegacyBehaviorWhenPiiIsEnabled(): void
    {
        $headers = ['Authorization' => ['secret']];

        $this->assertSame($headers, $this->legacyCollector(true)->collectHeaders($headers));
    }

    public function testCollectHeadersSanitizesConfiguredLegacyHeadersWhenPiiIsDisabled(): void
    {
        $collector = $this->legacyCollector(false, ['authorization']);

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Authorization-Token' => ['untouched'],
            'X-Request-Id' => ['request-id'],
        ], $collector->collectHeaders([
            'Authorization' => ['secret'],
            'X-Authorization-Token' => ['untouched'],
            'X-Request-Id' => ['request-id'],
        ]));
    }

    public function testCollectHeadersSupportsNumericNamesInLegacyMode(): void
    {
        $this->assertSame(
            ['123' => ['test']],
            $this->legacyCollector(false)->collectHeaders([123 => ['test']])
        );
    }

    public function testCollectHeadersUsesRequestHeaderBehavior(): void
    {
        $collector = $this->collector([
            'http_headers' => [
                'request' => [
                    'mode' => 'allowList',
                    'terms' => ['x-request-id'],
                ],
                'response' => ['mode' => 'off'],
            ],
        ]);

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ], $collector->collectHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
            'Host' => ['example.com'],
        ]));
    }

    public function testCollectHeadersReturnsNullWhenRequestHeadersAreDisabled(): void
    {
        $collector = $this->collector([
            'http_headers' => [
                'request' => ['mode' => 'off'],
                'response' => ['mode' => 'denyList'],
            ],
        ]);

        $this->assertNull($collector->collectHeaders(['X-Request-Id' => ['request-id']]));
    }

    public function testShouldCollectRequestBodyPreservesLegacyBehavior(): void
    {
        $this->assertTrue($this->legacyCollector(false)->shouldCollectRequestBody());
        $this->assertTrue($this->legacyCollector(true)->shouldCollectRequestBody());
    }

    public function testShouldCollectRequestBodyUsesIncomingRequestBodyType(): void
    {
        $this->assertTrue($this->collector(['http_bodies' => ['incomingRequest']])->shouldCollectRequestBody());
        $this->assertFalse($this->collector(['http_bodies' => []])->shouldCollectRequestBody());
        $this->assertFalse($this->collector(['http_bodies' => ['outgoingRequest']])->shouldCollectRequestBody());
    }

    public function testCollectRequestBodyPreservesLegacyBehavior(): void
    {
        $body = ['password' => 'secret'];

        $this->assertSame($body, $this->legacyCollector(false)->collectRequestBody($body));
        $this->assertSame('raw body', $this->legacyCollector(true)->collectRequestBody('raw body'));
    }

    public function testCollectRequestBodyFiltersStructuredSensitiveDataRecursively(): void
    {
        $collector = $this->collector(['http_bodies' => ['incomingRequest']]);

        $this->assertSame([
            'password' => '[Filtered]',
            'user' => [
                'api_token' => '[Filtered]',
                'name' => 'alice',
            ],
        ], $collector->collectRequestBody([
            'password' => 'secret',
            'user' => [
                'api_token' => 'token',
                'name' => 'alice',
            ],
        ]));
    }

    public function testCollectRequestBodyFiltersRawData(): void
    {
        $collector = $this->collector(['http_bodies' => ['incomingRequest']]);

        $this->assertSame('[Filtered]', $collector->collectRequestBody('raw body'));
    }

    public function testCollectRequestBodyReturnsNullWhenDisabledOrEmpty(): void
    {
        $disabled = $this->collector(['http_bodies' => []]);
        $enabled = $this->collector(['http_bodies' => ['incomingRequest']]);

        $this->assertNull($disabled->collectRequestBody('raw body'));
        $this->assertNull($enabled->collectRequestBody(''));
        $this->assertNull($enabled->collectRequestBody([]));
        $this->assertNull($enabled->collectRequestBody(null));
    }

    /**
     * @param string[] $piiSanitizeHeaders
     */
    private function legacyCollector(
        bool $sendDefaultPii,
        array $piiSanitizeHeaders = RequestDataCollector::DEFAULT_PII_SANITIZE_HEADERS
    ): RequestDataCollector {
        return new RequestDataCollector(null, $sendDefaultPii, $piiSanitizeHeaders);
    }

    /**
     * @param array<string, mixed> $dataCollection
     */
    private function collector(array $dataCollection): RequestDataCollector
    {
        return new RequestDataCollector(new DataCollectionOptions($dataCollection), false);
    }
}
