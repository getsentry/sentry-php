<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\RequestDataCollector;

final class RequestDataCollectorTest extends TestCase
{
    public function testUsesDataCollectionIsFalseInLegacyMode(): void
    {
        $piiEnabledCollector = $this->legacyCollector(true);
        $piiDisabledCollector = $this->legacyCollector(false);

        $this->assertFalse($piiEnabledCollector->usesDataCollection());
        $this->assertFalse($piiDisabledCollector->usesDataCollection());
    }

    public function testUsesDataCollectionIsTrueWhenConfigured(): void
    {
        $collector = $this->collector([]);

        $this->assertTrue($collector->usesDataCollection());
    }

    public function testShouldCollectUserInfoFollowsLegacySendDefaultPii(): void
    {
        $piiEnabledCollector = $this->legacyCollector(true);
        $piiDisabledCollector = $this->legacyCollector(false);

        $this->assertTrue($piiEnabledCollector->shouldCollectUserInfo());
        $this->assertFalse($piiDisabledCollector->shouldCollectUserInfo());
    }

    public function testShouldCollectUserInfoFollowsDataCollection(): void
    {
        $enabledCollector = $this->collector(['user_info' => true]);
        $disabledCollector = $this->collector(['user_info' => false]);

        $this->assertTrue($enabledCollector->shouldCollectUserInfo());
        $this->assertFalse($disabledCollector->shouldCollectUserInfo());
    }

    public function testShouldCollectUserInfoIgnoresSendDefaultPiiWhenDataCollectionIsConfigured(): void
    {
        $collector = new RequestDataCollector(new DataCollectionOptions(['user_info' => false]), true);

        $this->assertFalse($collector->shouldCollectUserInfo());
    }

    public function testCollectQueryStringInLegacyModeIsCollectedUntouched(): void
    {
        $piiEnabledCollector = $this->legacyCollector(true);
        $piiDisabledCollector = $this->legacyCollector(false);

        $collectedWithPii = $piiEnabledCollector->collectQueryString('token=secret');
        $collectedWithoutPii = $piiDisabledCollector->collectQueryString('token=secret');

        $this->assertSame('token=secret', $collectedWithPii);
        $this->assertSame('token=secret', $collectedWithoutPii);
    }

    public function testCollectQueryStringInLegacyModeSkipsEmptyValues(): void
    {
        $collector = $this->legacyCollector(true);

        $collected = $collector->collectQueryString('');

        $this->assertNull($collected);
    }

    public function testCollectQueryStringScrubsSensitiveParams(): void
    {
        $collector = $this->collector([]);

        $collected = $collector->collectQueryString('token=secret&page=5');

        $this->assertSame('token=[Filtered]&page=5', $collected);
    }

    public function testCollectQueryStringAppliesCustomDenyList(): void
    {
        $collector = $this->collector(['query_params' => ['mode' => 'denyList', 'terms' => ['page']]]);

        $collected = $collector->collectQueryString('page=5&foo=bar');

        $this->assertSame('page=[Filtered]&foo=bar', $collected);
    }

    public function testCollectQueryStringIsSkippedWhenModeIsOff(): void
    {
        $collector = $this->collector(['query_params' => ['mode' => 'off']]);

        $collected = $collector->collectQueryString('token=secret');

        $this->assertNull($collected);
    }

    public function testCollectQueryStringSkipsEmptyValues(): void
    {
        $collector = $this->collector([]);

        $collected = $collector->collectQueryString('');

        $this->assertNull($collected);
    }

    public function testCollectCookiesInLegacyModeCollectsRawCookiesWhenPiiIsEnabled(): void
    {
        $collector = $this->legacyCollector(true);
        $cookies = ['session_id' => 'secret'];

        $collected = $collector->collectCookies($cookies);

        $this->assertSame($cookies, $collected);
    }

    public function testCollectCookiesInLegacyModeIsSkippedWhenPiiIsDisabled(): void
    {
        $collector = $this->legacyCollector(false);

        $collected = $collector->collectCookies(['session_id' => 'secret']);

        $this->assertNull($collected);
    }

    public function testCollectCookiesScrubsSensitiveCookies(): void
    {
        $collector = $this->collector([]);

        $collected = $collector->collectCookies([
            'session_id' => 'secret',
            'theme' => 'dark',
        ]);

        $this->assertSame([
            'session_id' => '[Filtered]',
            'theme' => 'dark',
        ], $collected);
    }

    public function testCollectCookiesAppliesAllowList(): void
    {
        $collector = $this->collector(['cookies' => ['mode' => 'allowList', 'terms' => ['theme']]]);

        $collected = $collector->collectCookies([
            'theme' => 'dark',
            'tracking_id' => '12345',
        ]);

        $this->assertSame([
            'theme' => 'dark',
            'tracking_id' => '[Filtered]',
        ], $collected);
    }

    public function testCollectCookiesIsSkippedWhenModeIsOff(): void
    {
        $collector = $this->collector(['cookies' => ['mode' => 'off']]);

        $collected = $collector->collectCookies(['theme' => 'dark']);

        $this->assertNull($collected);
    }

    public function testCollectHeadersInLegacyModeCollectsRawHeadersWhenPiiIsEnabled(): void
    {
        $collector = $this->legacyCollector(true);
        $headers = ['Authorization' => ['secret']];

        $collected = $collector->collectHeaders($headers);

        $this->assertSame($headers, $collected);
    }

    public function testCollectHeadersInLegacyModeSanitizesConfiguredHeadersWhenPiiIsDisabled(): void
    {
        $collector = $this->legacyCollector(false, ['authorization']);

        $collected = $collector->collectHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ]);

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $collected);
    }

    public function testCollectHeadersInLegacyModeMatchesHeaderNamesExactly(): void
    {
        $collector = $this->legacyCollector(false, ['authorization']);

        // Unlike the data collection scrubbing, the legacy sanitization does
        // not match on partial header names
        $collected = $collector->collectHeaders(['X-Authorization-Token' => ['untouched']]);

        $this->assertSame(['X-Authorization-Token' => ['untouched']], $collected);
    }

    public function testCollectHeadersInLegacyModeSupportsNumericHeaderNames(): void
    {
        $collector = $this->legacyCollector(false);

        $collected = $collector->collectHeaders([123 => ['test']]);

        $this->assertSame(['123' => ['test']], $collected);
    }

    public function testCollectHeadersScrubsSensitiveHeaders(): void
    {
        $collector = $this->collector([]);

        $collected = $collector->collectHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ]);

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $collected);
    }

    public function testCollectHeadersAlwaysScrubsCookieHeaders(): void
    {
        $collector = $this->collector([]);

        $collected = $collector->collectHeaders([
            'Cookie' => ['session_id=secret; theme=dark'],
            'Set-Cookie' => ['session_id=secret'],
            'X-Request-Id' => ['request-id'],
        ]);

        $this->assertSame([
            'Cookie' => ['[Filtered]'],
            'Set-Cookie' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $collected);
    }

    public function testCollectHeadersAppliesExtendedDenyTermsToClientIpHeaders(): void
    {
        $defaultCollector = $this->collector([]);
        $extendedCollector = $this->collector(['http_headers' => ['request' => ['mode' => 'denyList', 'terms' => ['forwarded', '-ip']]]]);
        $headers = ['X-Forwarded-For' => ['203.0.113.7']];

        $this->assertSame($headers, $defaultCollector->collectHeaders($headers));
        $this->assertSame(['X-Forwarded-For' => ['[Filtered]']], $extendedCollector->collectHeaders($headers));
    }

    public function testCollectHeadersScrubsEveryLineOfSensitiveHeaders(): void
    {
        $collector = $this->collector([]);

        $collected = $collector->collectHeaders(['X-Api-Key' => ['first', 'second']]);

        $this->assertSame(['X-Api-Key' => ['[Filtered]', '[Filtered]']], $collected);
    }

    public function testCollectHeadersAppliesAllowList(): void
    {
        $collector = $this->collector(['http_headers' => ['request' => ['mode' => 'allowList', 'terms' => ['x-request-id']]]]);

        $collected = $collector->collectHeaders([
            'X-Request-Id' => ['request-id'],
            'Host' => ['www.example.com'],
        ]);

        $this->assertSame([
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ], $collected);
    }

    public function testCollectHeadersAllowListCannotOverrideMandatoryDenyList(): void
    {
        $collector = $this->collector(['http_headers' => ['request' => ['mode' => 'allowList', 'terms' => ['authorization']]]]);

        $collected = $collector->collectHeaders(['Authorization' => ['secret']]);

        $this->assertSame(['Authorization' => ['[Filtered]']], $collected);
    }

    public function testCollectHeadersIsSkippedWhenModeIsOff(): void
    {
        $collector = $this->collector(['http_headers' => ['request' => ['mode' => 'off']]]);

        $collected = $collector->collectHeaders(['X-Request-Id' => ['request-id']]);

        $this->assertNull($collected);
    }

    public function testShouldCollectRequestBodyIsAlwaysTrueInLegacyMode(): void
    {
        $piiEnabledCollector = $this->legacyCollector(true);
        $piiDisabledCollector = $this->legacyCollector(false);

        $this->assertTrue($piiEnabledCollector->shouldCollectRequestBody());
        $this->assertTrue($piiDisabledCollector->shouldCollectRequestBody());
    }

    public function testShouldCollectRequestBodyIsTrueWhenCollectingIncomingRequests(): void
    {
        $collector = $this->collector(['http_bodies' => ['incomingRequest']]);

        $this->assertTrue($collector->shouldCollectRequestBody());
    }

    public function testShouldCollectRequestBodyIsFalseWhenNotCollectingIncomingRequests(): void
    {
        $disabledCollector = $this->collector(['http_bodies' => []]);
        $outgoingOnlyCollector = $this->collector(['http_bodies' => ['outgoingRequest']]);

        $this->assertFalse($disabledCollector->shouldCollectRequestBody());
        $this->assertFalse($outgoingOnlyCollector->shouldCollectRequestBody());
    }

    public function testCollectRequestBodyInLegacyModeCollectsRawBody(): void
    {
        $piiEnabledCollector = $this->legacyCollector(true);
        $piiDisabledCollector = $this->legacyCollector(false);
        $body = ['password' => 'secret'];

        $collectedWithPii = $piiEnabledCollector->collectRequestBody($body);
        $collectedWithoutPii = $piiDisabledCollector->collectRequestBody('raw body');

        $this->assertSame($body, $collectedWithPii);
        $this->assertSame('raw body', $collectedWithoutPii);
    }

    public function testCollectRequestBodyCollectsRawBodyWhenCollectingIncomingRequests(): void
    {
        $collector = $this->collector(['http_bodies' => ['incomingRequest']]);
        $body = [
            'password' => 'secret',
            'username' => 'alice',
        ];

        $this->assertSame($body, $collector->collectRequestBody($body));
        $this->assertSame('[Filtered]', $collector->collectRequestBody('raw body'));
    }

    public function testCollectRequestBodyIsSkippedWhenNotCollectingIncomingRequests(): void
    {
        $collector = $this->collector(['http_bodies' => []]);

        $collected = $collector->collectRequestBody('raw body');

        $this->assertNull($collected);
    }

    public function testCollectRequestBodyInLegacyModeSkipsEmptyValues(): void
    {
        $collector = $this->legacyCollector(true);

        $this->assertNull($collector->collectRequestBody(''));
        $this->assertNull($collector->collectRequestBody([]));
        $this->assertNull($collector->collectRequestBody(null));
    }

    public function testCollectRequestBodySkipsEmptyValues(): void
    {
        $collector = $this->collector([]);

        $this->assertNull($collector->collectRequestBody(''));
        $this->assertNull($collector->collectRequestBody([]));
        $this->assertNull($collector->collectRequestBody(null));
    }

    /**
     * @param string[] $piiSanitizeHeaders
     */
    private function legacyCollector(bool $sendDefaultPii, array $piiSanitizeHeaders = RequestDataCollector::DEFAULT_PII_SANITIZE_HEADERS): RequestDataCollector
    {
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
