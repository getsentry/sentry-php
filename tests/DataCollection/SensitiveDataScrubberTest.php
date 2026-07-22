<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\SensitiveDataScrubber;

final class SensitiveDataScrubberTest extends TestCase
{
    public function testScrubKeyValueDataAppliesMandatoryDenyList(): void
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

    public function testScrubKeyValueDataCombinesMandatoryAndCustomDenyListTerms(): void
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

    public function testScrubKeyValueDataMatchesKeysCaseInsensitively(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $scrubbed = SensitiveDataScrubber::scrubKeyValueData(['AUTHORIZATION' => 'secret'], $behavior);

        $this->assertSame(['AUTHORIZATION' => '[Filtered]'], $scrubbed);
    }

    public function testScrubKeyValueDataAllowListScrubsEverythingElse(): void
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

    public function testScrubBodyDataScrubsSensitiveFields(): void
    {
        $scrubbed = SensitiveDataScrubber::scrubBodyData([
            'username' => 'alice',
            'password' => 'secret',
        ]);

        $this->assertSame([
            'username' => 'alice',
            'password' => '[Filtered]',
        ], $scrubbed);
    }

    public function testScrubBodyDataScrubsNestedFields(): void
    {
        $scrubbed = SensitiveDataScrubber::scrubBodyData([
            'nested' => ['api_token' => 'secret', 'plain' => 'visible'],
        ]);

        $this->assertSame([
            'nested' => ['api_token' => '[Filtered]', 'plain' => 'visible'],
        ], $scrubbed);
    }

    public function testScrubBodyDataIgnoresNumericKeys(): void
    {
        $scrubbed = SensitiveDataScrubber::scrubBodyData(['first', 'second']);

        $this->assertSame(['first', 'second'], $scrubbed);
    }
}
