<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\KeyValueDataFilter;

final class KeyValueDataFilterTest extends TestCase
{
    public function testFilterKeyValueDataReturnsNullWhenCollectionIsOff(): void
    {
        $behavior = ['mode' => 'off', 'terms' => ['public']];

        $this->assertNull(KeyValueDataFilter::filterKeyValueData([
            'authorization' => 'secret',
            'public' => 'visible',
        ], $behavior));
    }

    public function testFilterKeyValueDataAppliesMandatoryDenyList(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $filtered = KeyValueDataFilter::filterKeyValueData([
            'AUTHORIZATION' => 'secret',
            'public' => 'visible',
        ], $behavior);

        $this->assertSame([
            'AUTHORIZATION' => '[Filtered]',
            'public' => 'visible',
        ], $filtered);
    }

    public function testFilterKeyValueDataCombinesMandatoryAndCustomDenyListTerms(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['custom']];

        $filtered = KeyValueDataFilter::filterKeyValueData([
            'authorization' => 'secret',
            'custom-field' => 'private',
            'public' => 'visible',
        ], $behavior);

        $this->assertSame([
            'authorization' => '[Filtered]',
            'custom-field' => '[Filtered]',
            'public' => 'visible',
        ], $filtered);
    }

    public function testFilterKeyValueDataAppliesAllowList(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['theme']];

        $filtered = KeyValueDataFilter::filterKeyValueData([
            'preferred-theme' => 'dark',
            'tracking_id' => '12345',
        ], $behavior);

        $this->assertSame([
            'preferred-theme' => 'dark',
            'tracking_id' => '[Filtered]',
        ], $filtered);
    }

    public function testFilterKeyValueDataAllowListCannotOverrideMandatoryDenyList(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['authorization']];

        $filtered = KeyValueDataFilter::filterKeyValueData([
            'authorization' => 'secret',
        ], $behavior);

        $this->assertSame(['authorization' => '[Filtered]'], $filtered);
    }

    public function testFilterHeadersReturnsNullWhenCollectionIsOff(): void
    {
        $behavior = ['mode' => 'off', 'terms' => ['x-request-id']];

        $this->assertNull(KeyValueDataFilter::filterHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ], $behavior));
    }

    public function testFilterHeadersAppliesDenyListToEveryHeaderLine(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $filtered = KeyValueDataFilter::filterHeaders([
            'X-Api-Key' => ['first', 'second'],
            'X-Request-Id' => ['request-id'],
        ], $behavior);

        $this->assertSame([
            'X-Api-Key' => ['[Filtered]', '[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $filtered);
    }

    public function testFilterHeadersAlwaysFiltersCookieHeaders(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['cookie', 'set-cookie', 'x-request-id']];

        $filtered = KeyValueDataFilter::filterHeaders([
            'Cookie' => ['session_id=secret; theme=dark'],
            'Set-Cookie' => ['session_id=secret'],
            'X-Request-Id' => ['request-id'],
        ], $behavior);

        $this->assertSame([
            'Cookie' => ['[Filtered]'],
            'Set-Cookie' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $filtered);
    }

    public function testFilterHeadersAppliesExtendedDenyTerms(): void
    {
        $defaultBehavior = ['mode' => 'denyList', 'terms' => []];
        $extendedBehavior = ['mode' => 'denyList', 'terms' => ['forwarded', '-ip', 'remote-', 'via', '-user']];
        $headers = [
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
        ];

        $this->assertSame($headers, KeyValueDataFilter::filterHeaders($headers, $defaultBehavior));
        $this->assertSame([
            'X-Forwarded-For' => ['[Filtered]'],
            'X-Real-IP' => ['[Filtered]'],
        ], KeyValueDataFilter::filterHeaders($headers, $extendedBehavior));
    }

    public function testFilterHeadersAppliesAllowList(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['request-id']];

        $filtered = KeyValueDataFilter::filterHeaders([
            'X-Request-Id' => ['request-id'],
            'Host' => ['example.com'],
        ], $behavior);

        $this->assertSame([
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ], $filtered);
    }

    public function testFilterQueryStringReturnsNullWhenCollectionIsOff(): void
    {
        $behavior = ['mode' => 'off', 'terms' => ['page']];

        $this->assertNull(KeyValueDataFilter::filterQueryString('token=secret&page=1', $behavior));
    }

    public function testFilterQueryStringAppliesMandatoryAndCustomDenyListTerms(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['page']];

        $filtered = KeyValueDataFilter::filterQueryString('token=secret&page=1&flag', $behavior);

        $this->assertSame('token=[Filtered]&page=[Filtered]&flag', $filtered);
    }

    public function testFilterQueryStringDecodesKeysBeforeMatchingAndPreservesEncoding(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $filtered = KeyValueDataFilter::filterQueryString(
            'api%5Ftoken=secret&q=a%20b%26c&encoded%20field=encoded%2Bvalue',
            $behavior
        );

        $this->assertSame(
            'api%5Ftoken=[Filtered]&q=a%20b%26c&encoded%20field=encoded%2Bvalue',
            $filtered
        );
    }

    public function testFilterQueryStringPreservesValuelessParameters(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $filtered = KeyValueDataFilter::filterQueryString('token&token=&flag', $behavior);

        $this->assertSame('token&token=[Filtered]&flag', $filtered);
    }

    public function testFilterQueryStringDoesNotTreatCookieNamesAsCookieHeaders(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $filtered = KeyValueDataFilter::filterQueryString('cookie=foo&set-cookie=bar', $behavior);

        $this->assertSame('cookie=foo&set-cookie=bar', $filtered);
    }
}
