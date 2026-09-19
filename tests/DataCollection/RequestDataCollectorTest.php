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
     */
    public function testCollectUserInfoAndClientIpFollowConfiguration(?Options $options, bool $enabled): void
    {
        $collector = new RequestDataCollector(DataCollectionPolicy::fromOptions($options));
        $user = ['id' => 'alice', 'ip_address' => '203.0.113.7', 'impersonator_username' => 'admin'];

        $this->assertSame($enabled, $collector->shouldCollectUserInfo());
        $this->assertSame($enabled ? $user : [], $collector->collectUserInfo($user));
        $this->assertSame($enabled ? ['net.peer.ip' => '203.0.113.7'] : [], $collector->collectClientIpData('203.0.113.7'));
        $this->assertSame([], $collector->collectClientIpData(null));
    }

    /**
     * @return \Generator<string, array{Options|null, bool}>
     */
    public function userCollectionProvider(): \Generator
    {
        yield 'no options' => [null, false];
        yield 'legacy PII disabled' => [new Options(['send_default_pii' => false]), false];
        yield 'legacy PII enabled' => [new Options(['send_default_pii' => true]), true];
        yield 'configured defaults override PII disabled' => [new Options(['send_default_pii' => false, 'data_collection' => []]), true];
        yield 'configured user info disabled overrides PII enabled' => [new Options(['send_default_pii' => true, 'data_collection' => ['user_info' => false]]), false];
    }

    public function testCollectQueryStringPreservesLegacyBehavior(): void
    {
        $queryString = 'api%5Ftoken=secret&q=a%20b%26c';

        $this->assertSame($queryString, $this->legacyCollector(false)->collectQueryString($queryString));
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

    public function testDefaultHeaderRestrictionsDependOnCollectionMode(): void
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
            'Cookie' => ['[Filtered]'],
            'Set-Cookie' => ['[Filtered]'],
            'X-Forwarded-For' => ['[Filtered]'],
            'X-Real-IP' => ['[Filtered]'],
            'X-Authorization-Token' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ], $this->legacyCollector(false)->collectHeaders($headers));
        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'Proxy-Authorization' => ['[Filtered]'],
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
            'X-Authorization-Token' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $this->collector([])->collectHeaders($headers));
    }

    public function testExplicitlyEmptyHeaderRestrictionsPreserveLegacyHeaders(): void
    {
        $collector = new RequestDataCollector(DataCollectionPolicy::fromOptions(new Options()), []);
        $headers = ['Authorization' => ['secret'], 'X-Forwarded-For' => ['203.0.113.7']];

        $this->assertSame($headers, $collector->collectHeaders($headers));
    }

    public function testLegacyHeaderSanitizationDetachesReferencesWithoutChangingInput(): void
    {
        $authorization = 'secret';
        $requestId = 'request-id';
        $headers = ['Authorization' => [&$authorization], 'X-Request-Id' => [&$requestId]];

        $filtered = $this->legacyCollector(false)->collectHeaders($headers);

        $this->assertSame(['Authorization' => ['secret'], 'X-Request-Id' => ['request-id']], $headers);

        $authorization = 'new-secret';
        $requestId = 'new-request-id';

        $this->assertSame(['Authorization' => ['[Filtered]'], 'X-Request-Id' => ['request-id']], $filtered);
    }

    /**
     * @dataProvider collectorConfigurationProvider
     *
     * @param array<string, mixed> $configuration
     */
    public function testExplicitHeaderRestrictionsAreCaseInsensitiveAndMatchWholeNames(array $configuration): void
    {
        $policy = DataCollectionPolicy::fromOptions(new Options($configuration));
        $collector = new RequestDataCollector($policy, ['X-TeNaNt-Id']);

        $this->assertSame([
            'x-tenant-id' => ['[Filtered]', '[Filtered]'],
            'X-Tenant-ID-Label' => ['visible'],
            'X-Forwarded-For' => ['203.0.113.7'],
        ], $collector->collectHeaders([
            'x-tenant-id' => ['first', 'second'],
            'X-Tenant-ID-Label' => ['visible'],
            'X-Forwarded-For' => ['203.0.113.7'],
        ]));
    }

    public function collectorConfigurationProvider(): \Generator
    {
        yield 'legacy' => [[]];
        yield 'configured' => [['data_collection' => []]];
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

    public function testCookieHeadersAreExcludedEvenWhenExplicitlyAllowed(): void
    {
        $collector = $this->collector([
            'cookies' => ['mode' => 'off'],
            'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie', 'set-cookie', 'x-test']],
        ]);

        $this->assertSame(['X-Test' => ['visible']], $collector->collectHeaders([
            'CoOkIe' => ['theme=dark'],
            'SET-COOKIE' => ['malformed'],
            'X-Test' => ['visible'],
        ]));
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

    private function legacyCollector(bool $sendDefaultPii): RequestDataCollector
    {
        $policy = DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => $sendDefaultPii]));

        return new RequestDataCollector($policy);
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
