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
        $filtered = KeyValueDataFilter::filterKeyValueData([
            'AUTHORIZATION' => 'secret',
            'public' => 'visible',
        ], KeyValueDataFilter::DEFAULT_BEHAVIOR);

        $this->assertSame([
            'AUTHORIZATION' => '[Filtered]',
            'public' => 'visible',
        ], $filtered);
    }

    public function testFilterKeyValueDataCombinesMandatoryAndCustomDenyListTerms(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['custom-field']];

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
            'theme' => 'dark',
            'tracking_id' => '12345',
        ], $behavior);

        $this->assertSame([
            'theme' => 'dark',
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

    public function testFilterKeyValueDataFiltersNestedData(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => []];

        $filtered = KeyValueDataFilter::filterKeyValueData([
            'user' => [
                'password' => 'secret',
                'name' => 'alice',
            ],
        ], $behavior);

        $this->assertSame([
            'user' => [
                'password' => '[Filtered]',
                'name' => 'alice',
            ],
        ], $filtered);
    }

    public function testFilteringPreservesNamesAndSupportedValues(): void
    {
        $data = [
            2 => null,
            'profile' => ['enabled' => false, 'count' => 0, 'ratio' => 1.5, 'name' => ''],
        ];

        $this->assertSame($data, KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame([], KeyValueDataFilter::filterKeyValueData([], KeyValueDataFilter::DEFAULT_BEHAVIOR));
    }

    public function testFilteringDetachesReferences(): void
    {
        $name = 'Alice';
        $profile = ['name' => &$name];
        $data = ['profile' => &$profile];

        $filtered = KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR);
        $name = 'Bob';
        $profile['extra'] = 'changed';

        $this->assertSame(['profile' => ['name' => 'Alice']], $filtered);
    }

    public function testUnsupportedValuesNeverInvokeApplicationCode(): void
    {
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                throw new \LogicException('Must not serialize');
            }

            public function __toString(): string
            {
                throw new \LogicException('Must not cast');
            }
        };
        $resource = fopen('php://temp', 'w+');
        $data = [
            'object' => $object,
            'resource' => $resource,
            'callback' => static function (): void { throw new \LogicException('Must not invoke'); },
            'infinite' => \INF,
            'nan' => \NAN,
        ];

        try {
            $expected = array_fill_keys(array_keys($data), '[Filtered]');
            $this->assertSame($expected, KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR));
            $this->assertIsResource($resource);
        } finally {
            fclose($resource);
        }
    }

    /**
     * @dataProvider depthLimitProvider
     *
     * @param mixed $data
     * @param mixed $expected
     */
    public function testFilteringLimitsDepth(int $depth, $data, $expected): void
    {
        for ($level = 0; $level < $depth; ++$level) {
            $data = ['child' => $data];
            $expected = ['child' => $expected];
        }

        $this->assertSame($expected, KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame($expected['child'], KeyValueDataFilter::filterKeyValue('child', $data['child'], KeyValueDataFilter::DEFAULT_BEHAVIOR));
    }

    public function depthLimitProvider(): \Generator
    {
        yield 'below the limit' => [4, 'Alice', 'Alice'];
        yield 'at the limit' => [5, 'Alice', 'Alice'];
        yield 'scalar beyond the limit' => [6, 'Alice', '[Filtered]'];
        yield 'array beyond the limit' => [6, ['name' => 'Alice'], '[Filtered]'];
        yield 'sensitive values at the limit' => [4, ['name' => 'Alice', 'password' => 'secret'], ['name' => 'Alice', 'password' => '[Filtered]']];
    }

    /**
     * @requires PHP >= 7.4
     */
    public function testFilteringDetectsRecursiveArrays(): void
    {
        $data = ['name' => 'Alice', 'password' => 'secret'];
        $data['child'] = &$data;
        $expected = ['name' => 'Alice', 'password' => '[Filtered]', 'child' => '[Filtered]'];

        for ($depth = 0; $depth < 2; ++$depth) {
            $expected = ['name' => 'Alice', 'password' => '[Filtered]', 'child' => $expected];
        }

        $this->assertSame($expected, KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame($expected['child'], KeyValueDataFilter::filterKeyValue('child', $data['child'], KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame('secret', $data['child']['password']);
    }

    /**
     * @requires PHP < 7.4
     */
    public function testFilteringLimitsRecursiveArraysWithoutCycleDetection(): void
    {
        $data = ['name' => 'Alice', 'password' => 'secret'];
        $data['child'] = &$data;
        $expected = ['name' => '[Filtered]', 'password' => '[Filtered]', 'child' => '[Filtered]'];

        for ($depth = 0; $depth < 5; ++$depth) {
            $expected = ['name' => 'Alice', 'password' => '[Filtered]', 'child' => $expected];
        }

        $this->assertSame($expected, KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame($expected['child'], KeyValueDataFilter::filterKeyValue('child', $data['child'], KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame('secret', $data['child']['password']);
    }

    /**
     * @requires PHP >= 7.4
     */
    public function testFilteringDetectsCyclesThroughMultipleArraysAndNumericKeys(): void
    {
        $first = ['name' => 'Alice'];
        $second = [&$first];
        $first['child'] = &$second;
        $expected = ['name' => 'Alice', 'child' => [['name' => 'Alice', 'child' => '[Filtered]']]];

        $this->assertSame(['body' => $expected], KeyValueDataFilter::filterKeyValueData(['body' => $first], KeyValueDataFilter::DEFAULT_BEHAVIOR));
        $this->assertSame($expected, KeyValueDataFilter::filterKeyValue('body', $first, KeyValueDataFilter::DEFAULT_BEHAVIOR));
    }

    public function testFilteringPreservesSharedReferencesInSeparateBranches(): void
    {
        $profile = ['name' => 'Alice', 'password' => 'secret'];
        $shared = ['profile' => &$profile];
        $data = ['first' => &$shared, 'second' => &$shared, 'third' => ['profile' => &$profile]];
        $expected = array_fill_keys(['first', 'second', 'third'], ['profile' => ['name' => 'Alice', 'password' => '[Filtered]']]);
        $filtered = KeyValueDataFilter::filterKeyValueData($data, KeyValueDataFilter::DEFAULT_BEHAVIOR);
        $profile['name'] = 'Bob';

        $this->assertSame($expected, $filtered);
        $this->assertSame('secret', $profile['password']);
    }

    public function testAllowListAppliesToEveryArrayLevelIncludingNumericKeys(): void
    {
        $this->assertSame([
            'items' => [0 => '[Filtered]', 1 => ['name' => 'Alice', 'password' => '[Filtered]']],
        ], KeyValueDataFilter::filterKeyValueData([
            'items' => ['first', ['name' => 'Alice', 'password' => 'secret']],
        ], ['mode' => 'allowList', 'terms' => ['items', '1', 'name', 'password']]));
    }

    public function testNamedValuesUseTheSameRulesAsDictionaries(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['theme', 'session']];

        $this->assertSame('dark', KeyValueDataFilter::filterKeyValue('theme', 'dark', $behavior));
        $this->assertSame('[Filtered]', KeyValueDataFilter::filterKeyValue('session', 'secret', $behavior));
        $this->assertSame(['[Filtered]', '[Filtered]'], KeyValueDataFilter::filterKeyValue('theme', ['dark', 'light'], $behavior));
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

    public function testFilterHeadersDetachesReferencesWithoutChangingInput(): void
    {
        $authorization = 'secret';
        $requestId = 'request-id';
        $headers = [
            'Authorization' => [&$authorization],
            'X-Request-Id' => [&$requestId],
        ];

        $filtered = KeyValueDataFilter::filterHeaders($headers, KeyValueDataFilter::DEFAULT_BEHAVIOR);

        $this->assertSame([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ], $headers);

        $authorization = 'new-secret';
        $requestId = 'new-request-id';

        $this->assertSame([
            'Authorization' => ['[Filtered]'],
            'X-Request-Id' => ['request-id'],
        ], $filtered);
    }

    public function testFilterHeadersAlwaysExcludesCookieHeaders(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['cookie', 'set-cookie', 'x-request-id']];

        $filtered = KeyValueDataFilter::filterHeaders([
            'CoOkIe' => ['session_id=secret; theme=dark'],
            'SET-COOKIE' => ['session_id=secret'],
            'X-Request-Id' => ['request-id'],
        ], $behavior);

        $this->assertSame([
            'X-Request-Id' => ['request-id'],
        ], $filtered);
    }

    public function testFilterHeadersAppliesExtendedDenyTerms(): void
    {
        $defaultBehavior = ['mode' => 'denyList', 'terms' => []];
        $extendedBehavior = ['mode' => 'denyList', 'terms' => ['x-forwarded-for', 'x-real-ip']];
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
        $behavior = ['mode' => 'allowList', 'terms' => ['x-request-id']];

        $filtered = KeyValueDataFilter::filterHeaders([
            'X-Request-Id' => ['request-id'],
            'Host' => ['example.com'],
        ], $behavior);

        $this->assertSame([
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ], $filtered);
    }

    public function testCustomAllowTermsMatchWholeNames(): void
    {
        $behavior = ['mode' => 'allowList', 'terms' => ['THEME', 'api_token']];

        $this->assertSame([
            'theme' => 'dark',
            'user_theme' => '[Filtered]',
            'api_token' => '[Filtered]',
        ], KeyValueDataFilter::filterKeyValueData($this->customTermInput(), $behavior));
    }

    public function testCustomDenyTermsMatchSubstrings(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['THEME', 'api_token']];

        $this->assertSame([
            'theme' => '[Filtered]',
            'user_theme' => '[Filtered]',
            'api_token' => '[Filtered]',
        ], KeyValueDataFilter::filterKeyValueData($this->customTermInput(), $behavior));
    }

    public function testCustomTermsAreUsedForKeyValueData(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['THEME']];

        $this->assertSame([
            'theme' => '[Filtered]',
            'user_theme' => '[Filtered]',
            'api_token' => '[Filtered]',
        ], KeyValueDataFilter::filterKeyValueData($this->customTermInput(), $behavior));
    }

    public function testCustomTermsAreUsedForHeaders(): void
    {
        $behavior = ['mode' => 'denyList', 'terms' => ['THEME']];
        $headers = array_map(static function (string $value): array { return [$value]; }, $this->customTermInput());

        $this->assertSame([
            'theme' => ['[Filtered]'],
            'user_theme' => ['[Filtered]'],
            'api_token' => ['[Filtered]'],
        ], KeyValueDataFilter::filterHeaders($headers, $behavior));
    }

    /**
     * @dataProvider customQueryTermProvider
     *
     * @param array<string, mixed> $behavior
     */
    public function testCustomTermsAreUsedForQueryStrings(array $behavior, string $expected): void
    {
        $query = '%74heme=dark&user_theme=light&api_token=secret&q=a%20b';

        $this->assertSame($expected, KeyValueDataFilter::filterQueryString($query, $behavior));
    }

    public function customQueryTermProvider(): \Generator
    {
        yield 'allow list uses whole names' => [
            ['mode' => 'allowList', 'terms' => ['THEME', 'api_token']],
            '%74heme=dark&user_theme=[Filtered]&api_token=[Filtered]&q=[Filtered]',
        ];
        yield 'deny list uses partial names' => [
            ['mode' => 'denyList', 'terms' => ['THEME', 'api_token']],
            '%74heme=[Filtered]&user_theme=[Filtered]&api_token=[Filtered]&q=a%20b',
        ];
    }

    public function testEmptyCustomTermDoesNotMatchEveryName(): void
    {
        $this->assertSame('dark', KeyValueDataFilter::filterKeyValue('theme', 'dark', ['mode' => 'denyList', 'terms' => ['']]));
        $this->assertSame('[Filtered]', KeyValueDataFilter::filterKeyValue('theme', 'dark', ['mode' => 'allowList', 'terms' => ['']]));
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

    /**
     * @return array<string, string>
     */
    private function customTermInput(): array
    {
        return ['theme' => 'dark', 'user_theme' => 'light', 'api_token' => 'secret'];
    }
}
