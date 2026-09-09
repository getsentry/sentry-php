<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\RequestDataCollector;
use Sentry\Options;

final class RequestDataCollectorTest extends TestCase
{
    /**
     * @dataProvider userCollectionProvider
     *
     * @param array<string, mixed>|null $configuration
     */
    public function testCollectUserInfoAndClientIpFollowConfiguration(?array $configuration, bool $enabled): void
    {
        $collector = RequestDataCollector::fromOptions($configuration === null ? null : new Options($configuration));
        $data = ['id' => 'alice', 'ip_address' => '203.0.113.7', 'impersonator_username' => 'admin'];

        $this->assertSame($enabled ? $data : [], $collector->collectUserInfo($data));
        $this->assertSame($enabled ? ['net.peer.ip' => '203.0.113.7'] : [], $collector->collectClientIpData('203.0.113.7'));
        $this->assertSame([], $collector->collectClientIpData(null));
    }

    /**
     * @return \Generator<string, array{array<string, mixed>|null, bool}>
     */
    public function userCollectionProvider(): \Generator
    {
        yield 'no options' => [null, false];
        foreach ([false, true] as $pii) {
            $legacy = ['send_default_pii' => $pii];
            $suffix = ' pii=' . (int) $pii;
            yield 'legacy' . $suffix => [$legacy, $pii];
            yield 'null collection' . $suffix => [$legacy + ['data_collection' => null], $pii];
            yield 'configured defaults' . $suffix => [$legacy + ['data_collection' => []], true];
            yield 'unrelated override' . $suffix => [$legacy + ['data_collection' => ['http_headers' => ['mode' => 'off']]], true];
            yield 'user info enabled' . $suffix => [$legacy + ['data_collection' => ['user_info' => true]], true];
            yield 'user info disabled' . $suffix => [$legacy + ['data_collection' => ['user_info' => false]], false];
        }
    }

    public function testFactoryPreservesHeaderRestrictions(): void
    {
        foreach ([[], ['data_collection' => []]] as $configuration) {
            $collector = RequestDataCollector::fromOptions(new Options($configuration), ['x-tenant-id']);
            $this->assertSame([
                'X-Tenant-ID' => ['[Filtered]'],
                'X-Test' => ['visible'],
            ], $collector->collectHeaders(['X-Tenant-ID' => ['private'], 'X-Test' => ['visible']]));
        }
    }

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

    public function testCookieCollectionIsIndependentOfHeaders(): void
    {
        foreach (['off', 'denyList', 'allowList'] as $mode) {
            $collector = $this->collector([
                'cookies' => ['mode' => $mode, 'terms' => ['theme']],
                'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie', 'x-test']],
            ]);
            $this->assertSame(['X-Test' => ['visible']], $collector->collectHeaders([
                'CoOkIe' => ['theme=dark'],
                'SET-COOKIE' => ['malformed'],
                'X-Test' => ['visible'],
            ]));
        }

        $collector = $this->collector(['http_headers' => ['mode' => 'off']]);
        $this->assertNull($collector->collectHeaders(['Cookie' => ['theme=dark']]));
        $this->assertSame(['theme' => 'dark', 'session_id' => '[Filtered]'], $collector->collectCookies([
            'theme' => 'dark',
            'session_id' => 'secret',
        ]));
    }

    public function testExplicitHeaderRestrictionsArePreservedWithDataCollection(): void
    {
        $collector = new RequestDataCollector(new DataCollectionOptions(), true, ['x-tenant-id']);
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
