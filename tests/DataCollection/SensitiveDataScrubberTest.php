<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\SensitiveDataScrubber;

final class SensitiveDataScrubberTest extends TestCase
{
    public function testAlwaysScrubMandatoryValues(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubKeyValueData([
            'authorization' => 'secret',
            'public' => 'visible',
        ], $behavior);

        $this->assertSame([
            'authorization' => '[Filtered]',
            'public' => 'visible',
        ], $scrubbed);
    }

    public function testScrubCustomAndMandatory(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['custom']];

        $scrubbed = SensitiveDataScrubber::scrubKeyValueData([
            'authorization' => 'secret',
            'custom-field' => 'private',
            'public' => 'visible',
        ], $behavior);

        $this->assertSame([
            'authorization' => '[Filtered]',
            'custom-field' => '[Filtered]',
            'public' => 'visible',
        ], $scrubbed);
    }

    public function testScrubCaseInsensitiveKeys(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubKeyValueData(['AUTHORIZATION' => 'secret'], $behavior);

        $this->assertSame(['AUTHORIZATION' => '[Filtered]'], $scrubbed);
    }

    public function testAllowList(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['theme']];

        $scrubbed = SensitiveDataScrubber::scrubKeyValueData([
            'theme' => 'dark',
            'tracking_id' => '12345',
        ], $behavior);

        $this->assertSame([
            'theme' => 'dark',
            'tracking_id' => '[Filtered]',
        ], $scrubbed);
    }

    public function testScrubHeadersAppliesDenyList(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ], $behavior);

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $scrubbed);
    }

    public function testScrubHeadersScrubsEveryLineOfMatchingHeaders(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubHeaders(['X-Api-Key' => ['first', 'second']], $behavior);

        $this->assertSame(['X-Api-Key' => ['[Filtered]', '[Filtered]']], $scrubbed);
    }

    public function testScrubHeadersAlwaysScrubsCookieHeaders(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubHeaders([
            'Cookie' => ['session_id=secret; theme=dark'],
            'Set-Cookie' => ['session_id=secret'],
            'X-Request-Id' => ['request-id'],
        ], $behavior);

        $this->assertSame([
            'Cookie' => ['[Filtered]'],
            'Set-Cookie' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $scrubbed);
    }

    public function testScrubHeadersAllowListCannotOverrideCookieHeaders(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['cookie', 'set-cookie']];

        $scrubbed = SensitiveDataScrubber::scrubHeaders([
            'Cookie' => ['session_id=secret'],
            'Set-Cookie' => ['session_id=secret'],
        ], $behavior);

        $this->assertSame([
            'Cookie' => ['[Filtered]'],
            'Set-Cookie' => ['[Filtered]'],
        ], $scrubbed);
    }

    public function testExtendedDenyTerms(): void
    {
        $defaultBehavior = ['mode' => 'denyList', 'terms' => []];
        $extendedBehavior = ['mode' => 'denyList', 'terms' => ['forwarded', '-ip', 'remote-', 'via', '-user']];
        $headers = [
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
        ];

        $this->assertSame($headers, SensitiveDataScrubber::scrubHeaders($headers, $defaultBehavior));
        $this->assertSame([
            'X-Forwarded-For' => ['[Filtered]'],
            'X-Real-IP' => ['[Filtered]'],
        ], SensitiveDataScrubber::scrubHeaders($headers, $extendedBehavior));
    }

    public function testScrubHeadersAllowListCannotOverrideMandatoryDenyList(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['authorization', 'x-request-id']];

        $scrubbed = SensitiveDataScrubber::scrubHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
            'Host' => ['example.com'],
        ], $behavior);

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ], $scrubbed);
    }

    public function testScrubQueryStringAppliesMandatoryDenyList(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubQueryString('token=secret&page=1', $behavior);

        $this->assertSame('token=[Filtered]&page=1', $scrubbed);
    }

    public function testScrubQueryStringAppliesCustomDenyListTerms(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['page']];

        $scrubbed = SensitiveDataScrubber::scrubQueryString('token=secret&page=1&flag', $behavior);

        $this->assertSame('token=[Filtered]&page=[Filtered]&flag', $scrubbed);
    }

    public function testScrubQueryStringDecodesKeysBeforeMatching(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubQueryString('api%5Ftoken=secret&page=1', $behavior);

        $this->assertSame('api%5Ftoken=[Filtered]&page=1', $scrubbed);
    }

    public function testCookieNameIsAllowedInQueryParams(): void
    {
        $behaviour = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubQueryString('cookie=foo&set-cookie=bar', $behaviour);

        $this->assertSame('cookie=foo&set-cookie=bar', $scrubbed);
    }
}
