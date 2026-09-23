<?php

declare(strict_types=1);

namespace Sentry\Tests\DataCollection;

use PHPUnit\Framework\TestCase;
use Sentry\DataCollection\KeyValueCollectionBehavior;
use Sentry\DataCollection\KeyValueDataFilter;

final class KeyValueDataFilterTest extends TestCase
{
    public function testFilterKeyValueDataReturnsNullWhenCollectionIsOff(): void
    {
        $behavior = KeyValueCollectionBehavior::off();

        $this->assertNull((new KeyValueDataFilter($behavior))->filterKeyValueData([
            'authorization' => 'secret',
            'public' => 'visible',
        ]));
    }

    public function testFilterKeyValueDataAppliesMandatoryDenyList(): void
    {
        $filtered = (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData([
            'AUTHORIZATION' => 'secret',
            'public' => 'visible',
        ]);

        $this->assertSame([
            'AUTHORIZATION' => '[Filtered]',
            'public' => 'visible',
        ], $filtered);
    }

    public function testFilterKeyValueDataCombinesMandatoryAndCustomDenyListTerms(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList(['custom-field']);

        $filtered = (new KeyValueDataFilter($behavior))->filterKeyValueData([
            'authorization' => 'secret',
            'custom-field' => 'private',
            'public' => 'visible',
        ]);

        $this->assertSame([
            'authorization' => '[Filtered]',
            'custom-field' => '[Filtered]',
            'public' => 'visible',
        ], $filtered);
    }

    public function testFilterKeyValueDataAppliesAllowList(): void
    {
        $behavior = KeyValueCollectionBehavior::allowList(['theme']);

        $filtered = (new KeyValueDataFilter($behavior))->filterKeyValueData([
            'theme' => 'dark',
            'tracking_id' => '12345',
        ]);

        $this->assertSame([
            'theme' => 'dark',
            'tracking_id' => '[Filtered]',
        ], $filtered);
    }

    public function testFilterKeyValueDataAllowListCannotOverrideMandatoryDenyList(): void
    {
        $behavior = KeyValueCollectionBehavior::allowList(['authorization']);

        $filtered = (new KeyValueDataFilter($behavior))->filterKeyValueData([
            'authorization' => 'secret',
        ]);

        $this->assertSame(['authorization' => '[Filtered]'], $filtered);
    }

    public function testFilterKeyValueDataFiltersNestedData(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList();

        $filtered = (new KeyValueDataFilter($behavior))->filterKeyValueData([
            'user' => [
                'password' => 'secret',
                'name' => 'alice',
            ],
        ]);

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

        $this->assertSame($data, (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data));
        $this->assertSame([], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData([]));
    }

    public function testFilteringDetachesReferences(): void
    {
        $name = 'Alice';
        $profile = ['name' => &$name];
        $data = ['profile' => &$profile];

        $filtered = (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data);
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
            $this->assertSame($expected, (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data));
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

        $this->assertSame($expected, (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data));
        $this->assertSame([['child', $expected['child']]], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterPairs([['child', $data['child']]]));
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

        $this->assertSame($expected, (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data));
        $this->assertSame([['child', $expected['child']]], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterPairs([['child', $data['child']]]));
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

        $this->assertSame($expected, (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data));
        $this->assertSame([['child', $expected['child']]], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterPairs([['child', $data['child']]]));
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

        $this->assertSame(['body' => $expected], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData(['body' => $first]));
        $this->assertSame([['body', $expected]], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterPairs([['body', $first]]));
    }

    public function testFilteringPreservesSharedReferencesInSeparateBranches(): void
    {
        $profile = ['name' => 'Alice', 'password' => 'secret'];
        $shared = ['profile' => &$profile];
        $data = ['first' => &$shared, 'second' => &$shared, 'third' => ['profile' => &$profile]];
        $expected = array_fill_keys(['first', 'second', 'third'], ['profile' => ['name' => 'Alice', 'password' => '[Filtered]']]);
        $filtered = (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData($data);
        $profile['name'] = 'Bob';

        $this->assertSame($expected, $filtered);
        $this->assertSame('secret', $profile['password']);
    }

    public function testAllowListAppliesToEveryArrayLevelIncludingNumericKeys(): void
    {
        $this->assertSame([
            'items' => [0 => '[Filtered]', 1 => ['name' => 'Alice', 'password' => '[Filtered]']],
        ], (new KeyValueDataFilter(KeyValueCollectionBehavior::allowList(['items', '1', 'name', 'password'])))->filterKeyValueData([
            'items' => ['first', ['name' => 'Alice', 'password' => 'secret']],
        ]));
    }

    public function testNamedValuesUseTheSameRulesAsDictionaries(): void
    {
        $behavior = KeyValueCollectionBehavior::allowList(['theme', 'session']);

        $this->assertSame([['theme', 'dark']], (new KeyValueDataFilter($behavior))->filterPairs([['theme', 'dark']]));
        $this->assertSame([['session', '[Filtered]']], (new KeyValueDataFilter($behavior))->filterPairs([['session', 'secret']]));
        $this->assertSame([['theme', ['[Filtered]', '[Filtered]']]], (new KeyValueDataFilter($behavior))->filterPairs([['theme', ['dark', 'light']]]));
    }

    public function testFilterHeadersReturnsNullWhenCollectionIsOff(): void
    {
        $behavior = KeyValueCollectionBehavior::off();

        $this->assertNull((new KeyValueDataFilter($behavior))->filterHeaders([
            'Authorization' => ['secret'],
            'X-Request-Id' => ['request-id'],
        ]));
    }

    public function testFilterHeadersAppliesDenyListToEveryHeaderLine(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList();

        $filtered = (new KeyValueDataFilter($behavior))->filterHeaders([
            'X-Api-Key' => ['first', 'second'],
            'X-Request-Id' => ['request-id'],
        ]);

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

        $filtered = (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterHeaders($headers);

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
        $behavior = KeyValueCollectionBehavior::allowList(['cookie', 'set-cookie', 'x-request-id']);

        $filtered = (new KeyValueDataFilter($behavior))->filterHeaders([
            'CoOkIe' => ['session_id=secret; theme=dark'],
            'SET-COOKIE' => ['session_id=secret'],
            'X-Request-Id' => ['request-id'],
        ]);

        $this->assertSame([
            'X-Request-Id' => ['request-id'],
        ], $filtered);
    }

    public function testFilterHeadersAppliesExtendedDenyTerms(): void
    {
        $defaultBehavior = KeyValueCollectionBehavior::denyList();
        $extendedBehavior = KeyValueCollectionBehavior::denyList(['x-forwarded-for', 'x-real-ip']);
        $headers = [
            'X-Forwarded-For' => ['203.0.113.7'],
            'X-Real-IP' => ['203.0.113.7'],
        ];

        $this->assertSame($headers, (new KeyValueDataFilter($defaultBehavior))->filterHeaders($headers));
        $this->assertSame([
            'X-Forwarded-For' => ['[Filtered]'],
            'X-Real-IP' => ['[Filtered]'],
        ], (new KeyValueDataFilter($extendedBehavior))->filterHeaders($headers));
    }

    public function testFilterHeadersAppliesAllowList(): void
    {
        $behavior = KeyValueCollectionBehavior::allowList(['x-request-id']);

        $filtered = (new KeyValueDataFilter($behavior))->filterHeaders([
            'X-Request-Id' => ['request-id'],
            'Host' => ['example.com'],
        ]);

        $this->assertSame([
            'X-Request-Id' => ['request-id'],
            'Host' => ['[Filtered]'],
        ], $filtered);
    }

    public function testCustomAllowTermsMatchWholeNames(): void
    {
        $behavior = KeyValueCollectionBehavior::allowList(['THEME', 'api_token']);

        $this->assertSame([
            'theme' => 'dark',
            'user_theme' => '[Filtered]',
            'api_token' => '[Filtered]',
        ], (new KeyValueDataFilter($behavior))->filterKeyValueData($this->customTermInput()));
    }

    public function testCustomDenyTermsMatchSubstrings(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList(['THEME', 'api_token']);

        $this->assertSame([
            'theme' => '[Filtered]',
            'user_theme' => '[Filtered]',
            'api_token' => '[Filtered]',
        ], (new KeyValueDataFilter($behavior))->filterKeyValueData($this->customTermInput()));
    }

    public function testCustomTermsAreUsedForKeyValueData(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList(['THEME']);

        $this->assertSame([
            'theme' => '[Filtered]',
            'user_theme' => '[Filtered]',
            'api_token' => '[Filtered]',
        ], (new KeyValueDataFilter($behavior))->filterKeyValueData($this->customTermInput()));
    }

    public function testCustomTermsAreUsedForHeaders(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList(['THEME']);
        $headers = array_map(static function (string $value): array { return [$value]; }, $this->customTermInput());

        $this->assertSame([
            'theme' => ['[Filtered]'],
            'user_theme' => ['[Filtered]'],
            'api_token' => ['[Filtered]'],
        ], (new KeyValueDataFilter($behavior))->filterHeaders($headers));
    }

    /**
     * @dataProvider customQueryTermProvider
     */
    public function testCustomTermsAreUsedForQueryStrings(KeyValueCollectionBehavior $behavior, string $expected): void
    {
        $query = '%74heme=dark&user_theme=light&api_token=secret&q=a%20b';

        $this->assertSame($expected, (new KeyValueDataFilter($behavior))->filterQueryString($query));
    }

    public function customQueryTermProvider(): \Generator
    {
        yield 'allow list uses whole names' => [
            KeyValueCollectionBehavior::allowList(['THEME', 'api_token']),
            '%74heme=dark&user_theme=[Filtered]&api_token=[Filtered]&q=[Filtered]',
        ];
        yield 'deny list uses partial names' => [
            KeyValueCollectionBehavior::denyList(['THEME', 'api_token']),
            '%74heme=[Filtered]&user_theme=[Filtered]&api_token=[Filtered]&q=a%20b',
        ];
    }

    public function testEmptyCustomTermDoesNotMatchEveryName(): void
    {
        $this->assertSame([['theme', 'dark']], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList([''])))->filterPairs([['theme', 'dark']]));
        $this->assertSame([['theme', '[Filtered]']], (new KeyValueDataFilter(KeyValueCollectionBehavior::allowList([''])))->filterPairs([['theme', 'dark']]));
    }

    public function testFilterQueryStringReturnsNullWhenCollectionIsOff(): void
    {
        $behavior = KeyValueCollectionBehavior::off();

        $this->assertNull((new KeyValueDataFilter($behavior))->filterQueryString('token=secret&page=1'));
    }

    public function testFilterQueryStringAppliesMandatoryAndCustomDenyListTerms(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList(['page']);

        $filtered = (new KeyValueDataFilter($behavior))->filterQueryString('token=secret&page=1&flag');

        $this->assertSame('token=[Filtered]&page=[Filtered]&flag', $filtered);
    }

    public function testFilterQueryStringDecodesKeysBeforeMatchingAndPreservesEncoding(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList();

        $filtered = (new KeyValueDataFilter($behavior))->filterQueryString(
            'api%5Ftoken=secret&q=a%20b%26c&encoded%20field=encoded%2Bvalue');

        $this->assertSame(
            'api%5Ftoken=[Filtered]&q=a%20b%26c&encoded%20field=encoded%2Bvalue',
            $filtered
        );
    }

    public function testFilterQueryStringPreservesValuelessParameters(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList();

        $filtered = (new KeyValueDataFilter($behavior))->filterQueryString('token&token=&flag');

        $this->assertSame('token&token=[Filtered]&flag', $filtered);
    }

    public function testFilterQueryStringDoesNotTreatCookieNamesAsCookieHeaders(): void
    {
        $behavior = KeyValueCollectionBehavior::denyList();

        $filtered = (new KeyValueDataFilter($behavior))->filterQueryString('cookie=foo&set-cookie=bar');

        $this->assertSame('cookie=foo&set-cookie=bar', $filtered);
    }

    public function testOffDoesNotCollectAnything(): void
    {
        $filter = new KeyValueDataFilter(KeyValueCollectionBehavior::off());

        $this->assertFalse($filter->isEnabled());
        $this->assertNull($filter->filterKeyValueData(['theme' => 'dark']));
        $this->assertNull($filter->filterPairs([['theme', 'dark']]));
        $this->assertNull($filter->filterHeaders(['X-Request-Id' => ['request-id']]));
        $this->assertNull($filter->filterQueryString('page=1'));
    }

    public function testCollectingModesAreEnabled(): void
    {
        $this->assertTrue((new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->isEnabled());
        $this->assertTrue((new KeyValueDataFilter(KeyValueCollectionBehavior::allowList()))->isEnabled());
    }

    public function testFilterPairsKeepsDuplicateNamesAndOrder(): void
    {
        $this->assertSame([
            ['theme', 'dark'],
            ['session_id', '[Filtered]'],
            ['theme', 'light'],
        ], (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterPairs([
            ['theme', 'dark'],
            ['session_id', 'secret'],
            ['theme', 'light'],
        ]));
    }

    public function testSerializeValueIsOnlyCalledForKeysThatAreNotFiltered(): void
    {
        $serializedKeys = [];
        $serializeValue = static function (array $value) use (&$serializedKeys): array {
            $serializedKeys[] = $value['key'];

            return ['name' => $value['key'], 'password' => 'introduced by serialization'];
        };

        $filtered = (new KeyValueDataFilter(KeyValueCollectionBehavior::denyList()))->filterKeyValueData([
            'profile' => ['key' => 'profile'],
            'password' => ['key' => 'password'],
        ], $serializeValue);

        $this->assertSame(['profile'], $serializedKeys);
        $this->assertSame([
            'profile' => ['name' => 'profile', 'password' => '[Filtered]'],
            'password' => '[Filtered]',
        ], $filtered);
    }

    public function testSerializeValueIsNotCalledWhenCollectionIsOff(): void
    {
        $calls = 0;

        $this->assertNull((new KeyValueDataFilter(KeyValueCollectionBehavior::off()))->filterKeyValueData(
            ['theme' => 'dark'],
            static function ($value) use (&$calls) {
                ++$calls;

                return $value;
            }
        ));
        $this->assertSame(0, $calls);
    }

    /**
     * @return array<string, string>
     */
    private function customTermInput(): array
    {
        return ['theme' => 'dark', 'user_theme' => 'light', 'api_token' => 'secret'];
    }
}
