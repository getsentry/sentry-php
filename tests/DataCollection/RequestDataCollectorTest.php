<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\RequestDataCollector;
use Sentry\Options;

final class RequestDataCollectorTest extends TestCase
{
    /**
     * @dataProvider userCollectionProvider
     *
     * @param array<string, mixed> $expectedUser
     * @param array<string, mixed> $expectedIp
     */
    public function testCollectUserInfoAndClientIpFollowConfiguration(?Options $options, array $expectedUser, array $expectedIp): void
    {
        $collector = new RequestDataCollector(DataCollectionPolicy::fromOptions($options));
        $user = ['id' => 'alice', 'ip_address' => '203.0.113.7', 'impersonator_username' => 'admin'];

        $this->assertSame($expectedUser, $collector->collectUserInfo($user));
        $this->assertSame($expectedIp, $collector->collectClientIpData('203.0.113.7'));
        $this->assertSame([], $collector->collectClientIpData(null));
    }

    /**
     * @return \Generator<string, array{Options|null, array<string, mixed>, array<string, mixed>}>
     */
    public function userCollectionProvider(): \Generator
    {
        $user = ['id' => 'alice', 'ip_address' => '203.0.113.7', 'impersonator_username' => 'admin'];
        $ip = ['net.peer.ip' => '203.0.113.7'];

        yield 'no options' => [null, [], []];
        yield 'legacy PII disabled' => [new Options(['send_default_pii' => false]), [], []];
        yield 'legacy PII enabled' => [new Options(['send_default_pii' => true]), $user, $ip];
        yield 'null collection with PII disabled' => [new Options(['send_default_pii' => false, 'data_collection' => null]), [], []];
        yield 'null collection with PII enabled' => [new Options(['send_default_pii' => true, 'data_collection' => null]), $user, $ip];
        yield 'configured defaults override PII disabled' => [new Options(['send_default_pii' => false, 'data_collection' => []]), $user, $ip];
        yield 'configured defaults ignore PII enabled' => [new Options(['send_default_pii' => true, 'data_collection' => []]), $user, $ip];
        yield 'unrelated configuration keeps user info enabled' => [new Options(['send_default_pii' => false, 'data_collection' => ['http_headers' => ['mode' => 'off']]]), $user, $ip];
        yield 'user info enabled' => [new Options(['data_collection' => ['user_info' => true]]), $user, $ip];
        yield 'user info disabled' => [new Options(['data_collection' => ['user_info' => false]]), [], []];
    }

    /**
     * @dataProvider collectorConfigurationProvider
     *
     * @param array<string, mixed> $configuration
     */
    public function testCollectorPreservesHeaderRestrictions(array $configuration): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options($configuration));
        $collector = new RequestDataCollector($policy, ['x-tenant-id']);

        $this->assertSame([
            'X-Tenant-ID' => ['[Filtered]'],
            'X-Test' => ['visible'],
        ], $collector->collectHeaders(['X-Tenant-ID' => ['private'], 'X-Test' => ['visible']]));
    }

    public function collectorConfigurationProvider(): \Generator
    {
        yield 'legacy' => [[]];
        yield 'configured' => [['data_collection' => []]];
    }

    public function testShouldCollectUserInfoFollowsLegacySendDefaultPii(): void
    {
        $this->assertFalse($this->legacyCollector(false)->shouldCollectUserInfo());
        $this->assertTrue($this->legacyCollector(true)->shouldCollectUserInfo());
    }

    public function testShouldCollectUserInfoUsesDataCollectionWhenConfigured(): void
    {
        $enabled = $this->collector(['user_info' => true]);
        $disabled = $this->collector(['user_info' => false]);

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

    public function testCookieAllowListPreservesRepeatedValues(): void
    {
        $collector = $this->collector(['cookies' => ['mode' => 'allowList', 'terms' => ['theme', 'session']]]);
        $this->assertSame([
            'theme' => ['dark', 'light'],
            'session' => '[Filtered]',
            'language' => '[Filtered]',
        ], $collector->collectCookies([
            'theme' => ['dark', 'light'],
            'session' => ['one', 'two'],
            'language' => ['en', 'de'],
        ]));
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

    public function testMalformedCookieFallbackUsesCookiePolicy(): void
    {
        $fallback = ['Cookie' => ['[Filtered]']];

        $this->assertSame($fallback, $this->collector([])->collectMalformedCookieHeader(['malformed']));
        $this->assertSame($fallback, $this->collector(['http_headers' => ['mode' => 'off']])->collectMalformedCookieHeader(['malformed']));
        $this->assertSame([], $this->collector(['cookies' => ['mode' => 'off']])->collectMalformedCookieHeader(['malformed']));
        $this->assertSame([], $this->collector([])->collectMalformedCookieHeader(['theme=dark']));
        $this->assertSame([], $this->legacyCollector(false)->collectMalformedCookieHeader(['malformed']));
        $this->assertSame([], $this->legacyCollector(true)->collectMalformedCookieHeader(['malformed']));
    }

    /**
     * @dataProvider cookieModeProvider
     */
    public function testCookieHeadersAreExcludedFromHeaderCollection(string $cookieMode): void
    {
        $collector = $this->collector([
            'cookies' => ['mode' => $cookieMode, 'terms' => ['theme']],
            'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie', 'x-test']],
        ]);

        $this->assertSame(['X-Test' => ['visible']], $collector->collectHeaders([
            'CoOkIe' => ['theme=dark'],
            'SET-COOKIE' => ['malformed'],
            'X-Test' => ['visible'],
        ]));
    }

    public function cookieModeProvider(): \Generator
    {
        yield 'off' => ['off'];
        yield 'deny list' => ['denyList'];
        yield 'allow list' => ['allowList'];
    }

    public function testCookiesAreCollectedWhenHeadersAreDisabled(): void
    {
        $collector = $this->collector(['http_headers' => ['mode' => 'off']]);

        $this->assertNull($collector->collectHeaders(['Cookie' => ['theme=dark']]));
        $this->assertSame(['theme' => 'dark', 'session_id' => '[Filtered]'], $collector->collectCookies([
            'theme' => 'dark',
            'session_id' => 'secret',
        ]));
    }

    public function testExplicitHeaderRestrictionsArePreservedWithDataCollection(): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['data_collection' => []]));
        $collector = new RequestDataCollector($policy, ['x-tenant-id']);
        $this->assertSame([
            'X-Tenant-ID' => ['[Filtered]'],
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Tenant-ID-Label' => ['visible'],
        ], $collector->collectHeaders([
            'X-Tenant-ID' => ['tenant'],
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Tenant-ID-Label' => ['visible'],
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

    /**
     * @param string[] $piiSanitizeHeaders
     */
    private function legacyCollector(
        bool $sendDefaultPii,
        array $piiSanitizeHeaders = RequestDataCollector::DEFAULT_PII_SANITIZE_HEADERS
    ): RequestDataCollector {
        $policy = DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => $sendDefaultPii]));

        return new RequestDataCollector($policy, $piiSanitizeHeaders);
    }

    /**
     * @param array<string, mixed> $dataCollection
     */
    private function collector(array $dataCollection): RequestDataCollector
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));

        return new RequestDataCollector($policy);
    }
}
