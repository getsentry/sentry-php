<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpHeaderCollector;
use Sentry\DataCollection\HttpMessageType;
use Sentry\Options;

final class HttpHeaderCollectorTest extends TestCase
{
    public function testLegacyModeWithPiiCollectsIncomingRequestHeadersUnfiltered(): void
    {
        $headers = ['Authorization' => ['secret'], 'Cookie' => ['session_id=secret']];

        $this->assertSame($headers, HttpHeaderCollector::collect($this->legacyPolicy(true), HttpMessageType::incomingRequest(), $headers));
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
        ], HttpHeaderCollector::collect($this->legacyPolicy(false), HttpMessageType::incomingRequest(), $headers));
        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'Proxy-Authorization' => ['[Filtered]'],
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
            'X-Authorization-Token' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], HttpHeaderCollector::collect($this->policy([]), HttpMessageType::incomingRequest(), $headers));
    }

    /**
     * @dataProvider legacyUncollectedTypeProvider
     */
    public function testLegacyModeDoesNotCollectOtherHeaders(HttpMessageType $type): void
    {
        $this->assertNull(HttpHeaderCollector::collect($this->legacyPolicy(true), $type, ['X-Request-Id' => ['request-id']]));
    }

    public static function legacyUncollectedTypeProvider(): \Generator
    {
        yield 'outgoing request' => [HttpMessageType::outgoingRequest()];
        yield 'incoming response' => [HttpMessageType::incomingResponse()];
        yield 'outgoing response' => [HttpMessageType::outgoingResponse()];
    }

    public function testExplicitlyEmptyHeaderRestrictionsPreserveLegacyHeaders(): void
    {
        $headers = ['Authorization' => ['secret'], 'X-Forwarded-For' => ['203.0.113.7']];

        $this->assertSame($headers, HttpHeaderCollector::collect($this->legacyPolicy(false), HttpMessageType::incomingRequest(), $headers, []));
    }

    public function testLegacyHeaderSanitizationDetachesReferencesWithoutChangingInput(): void
    {
        $authorization = 'secret';
        $requestId = 'request-id';
        $headers = ['Authorization' => [&$authorization], 'X-Request-Id' => [&$requestId]];

        $filtered = HttpHeaderCollector::collect($this->legacyPolicy(false), HttpMessageType::incomingRequest(), $headers);

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

        $this->assertSame([
            'x-tenant-id' => ['[Filtered]', '[Filtered]'],
            'X-Tenant-ID-Label' => ['visible'],
            'X-Forwarded-For' => ['203.0.113.7'],
        ], HttpHeaderCollector::collect($policy, HttpMessageType::incomingRequest(), [
            'x-tenant-id' => ['first', 'second'],
            'X-Tenant-ID-Label' => ['visible'],
            'X-Forwarded-For' => ['203.0.113.7'],
        ], ['X-TeNaNt-Id']));
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
            HttpHeaderCollector::collect($this->legacyPolicy(false), HttpMessageType::incomingRequest(), [123 => ['test']])
        );
    }

    public function testRequestAndResponseHeadersUseTheirOwnBehavior(): void
    {
        $policy = $this->policy([
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

        $this->assertSame($expected, HttpHeaderCollector::collect($policy, HttpMessageType::incomingRequest(), $headers));
        $this->assertSame($expected, HttpHeaderCollector::collect($policy, HttpMessageType::outgoingRequest(), $headers));
        $this->assertNull(HttpHeaderCollector::collect($policy, HttpMessageType::incomingResponse(), $headers));
        $this->assertNull(HttpHeaderCollector::collect($policy, HttpMessageType::outgoingResponse(), $headers));
    }

    public function testCookieHeadersAreExcludedEvenWhenExplicitlyAllowed(): void
    {
        $policy = $this->policy([
            'cookies' => ['mode' => 'off'],
            'http_headers' => ['mode' => 'allowList', 'terms' => ['cookie', 'set-cookie', 'x-test']],
        ]);

        $this->assertSame(['X-Test' => ['visible']], HttpHeaderCollector::collect($policy, HttpMessageType::incomingRequest(), [
            'CoOkIe' => ['theme=dark'],
            'SET-COOKIE' => ['malformed'],
            'X-Test' => ['visible'],
        ]));
    }

    public function testHeadersAreNotCollectedWhenDisabled(): void
    {
        $policy = $this->policy(['http_headers' => ['mode' => 'off']]);

        $this->assertNull(HttpHeaderCollector::collect($policy, HttpMessageType::incomingRequest(), ['Cookie' => ['theme=dark']], ['x-tenant-id']));
    }

    private function legacyPolicy(bool $sendDefaultPii): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['send_default_pii' => $sendDefaultPii]));
    }

    /**
     * @param array<string, mixed> $dataCollection
     */
    private function policy(array $dataCollection): DataCollectionPolicy
    {
        return DataCollectionPolicy::fromOptions(new Options(['data_collection' => $dataCollection]));
    }
}
