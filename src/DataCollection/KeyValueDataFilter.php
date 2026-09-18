<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * @phpstan-type KeyValueCollectionBehavior array{mode: 'off'|'denyList'|'allowList', terms: string[]}
 */
final class KeyValueDataFilter
{
    public const FILTERED_VALUE = '[Filtered]';

    /**
     * Equivalent to `true`. Used when all items are collected.
     */
    public const DEFAULT_BEHAVIOR = [
        'mode' => 'denyList',
        'terms' => [],
    ];

    /**
     * Values deeper than this limit are filtered. The top-level dictionary has depth 0.
     */
    private const MAX_DEPTH = 5;

    private const SENSITIVE_DATA_DENYLIST = [
        'auth',
        'token',
        'secret',
        'password',
        'passwd',
        'pwd',
        'key',
        'jwt',
        'bearer',
        'sso',
        'saml',
        'csrf',
        'xsrf',
        'credentials',
        'session',
        'sid',
        'identity',
    ];

    private const EXCLUDED_HEADERS = [
        'cookie',
        'set-cookie',
    ];

    /**
     * @var string|null
     */
    private static $sensitiveDataDenyListRegex;

    private function __construct()
    {
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return array<array-key, string[]>|null null means disabled
     */
    public static function filterHeaders(array $headers, array $behavior): ?array
    {
        if ($behavior['mode'] === 'off') {
            return null;
        }

        $filtered = [];

        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (\in_array(strtolower($name), self::EXCLUDED_HEADERS, true)) {
                continue;
            }

            $shouldFilter = self::shouldFilterValue($name, $behavior);
            $filtered[$name] = [];

            foreach ($values as $headerLine => $headerValue) {
                $filtered[$name][$headerLine] = $shouldFilter ? self::FILTERED_VALUE : $headerValue;
            }
        }

        return $filtered;
    }

    /**
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $data
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return array<TKey, mixed>|null null means disabled
     */
    public static function filterKeyValueData(array $data, array $behavior): ?array
    {
        if ($behavior['mode'] === 'off') {
            return null;
        }

        $filtered = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($data as $key => $value) {
            $filtered[$key] = self::filterKeyValue((string) $key, $value, $behavior);
        }

        return $filtered;
    }

    /**
     * @param mixed $value
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return mixed
     */
    public static function filterKeyValue(string $key, $value, array $behavior)
    {
        if (\PHP_VERSION_ID >= 70400) {
            return self::filterKeyValueWithCycleDetection($key, $value, $behavior, 1);
        }

        return self::filterKeyValueWithDepthLimit($key, $value, $behavior, 1);
    }

    /**
     * @phpstan-param KeyValueCollectionBehavior $behavior
     */
    public static function filterQueryString(string $queryString, array $behavior): ?string
    {
        if ($behavior['mode'] === 'off') {
            return null;
        }

        $parts = explode('&', $queryString);

        foreach ($parts as $index => $part) {
            $separatorPosition = strpos($part, '=');
            if ($separatorPosition === false) {
                continue;
            }

            $encodedKey = substr($part, 0, $separatorPosition);
            $key = urldecode($encodedKey);

            if (self::shouldFilterValue($key, $behavior)) {
                $parts[$index] = $encodedKey . '=' . self::FILTERED_VALUE;
            }
        }

        return implode('&', $parts);
    }

    /**
     * @phpstan-param KeyValueCollectionBehavior $behavior
     */
    public static function shouldFilterValue(string $key, array $behavior): bool
    {
        if ($behavior['mode'] === 'off' || self::matchesMandatoryDenyList($key)) {
            return true;
        }

        if ($behavior['mode'] === 'allowList') {
            return !self::matchesAnyTerm($key, $behavior['terms'], false);
        }

        return self::matchesAnyTerm($key, $behavior['terms'], true);
    }

    /**
     * @param mixed               $value
     * @param array<string, true> $references References on the current recursion path
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return mixed
     */
    private static function filterKeyValueWithCycleDetection(string $key, $value, array $behavior, int $depth, array $references = [])
    {
        if ($depth > self::MAX_DEPTH || self::shouldFilterValue($key, $behavior)) {
            return self::FILTERED_VALUE;
        }

        if (\is_array($value)) {
            $filtered = [];

            /** @mago-ignore analysis:mixed-assignment */
            foreach ($value as $childKey => $childValue) {
                $childReferences = $references;
                if (\is_array($childValue)) {
                    $reference = \ReflectionReference::fromArrayElement($value, $childKey);
                    if ($reference !== null) {
                        $referenceId = $reference->getId();
                        if (isset($references[$referenceId])) {
                            $filtered[$childKey] = self::FILTERED_VALUE;

                            continue;
                        }

                        $childReferences[$referenceId] = true;
                    }
                }

                $filtered[$childKey] = self::filterKeyValueWithCycleDetection((string) $childKey, $childValue, $behavior, $depth + 1, $childReferences);
            }

            return $filtered;
        }

        if (($value !== null && !\is_scalar($value)) || (\is_float($value) && !is_finite($value))) {
            return self::FILTERED_VALUE;
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return mixed
     */
    private static function filterKeyValueWithDepthLimit(string $key, $value, array $behavior, int $depth)
    {
        if ($depth > self::MAX_DEPTH || self::shouldFilterValue($key, $behavior)) {
            return self::FILTERED_VALUE;
        }

        if (\is_array($value)) {
            $filtered = [];

            /** @mago-ignore analysis:mixed-assignment */
            foreach ($value as $childKey => $childValue) {
                $filtered[$childKey] = self::filterKeyValueWithDepthLimit((string) $childKey, $childValue, $behavior, $depth + 1);
            }

            return $filtered;
        }

        if (($value !== null && !\is_scalar($value)) || (\is_float($value) && !is_finite($value))) {
            return self::FILTERED_VALUE;
        }

        return $value;
    }

    private static function matchesMandatoryDenyList(string $key): bool
    {
        if (self::$sensitiveDataDenyListRegex === null) {
            self::$sensitiveDataDenyListRegex = '/' . implode('|', array_map(static function (string $term): string {
                return preg_quote($term, '/');
            }, self::SENSITIVE_DATA_DENYLIST)) . '/i';
        }

        return preg_match(self::$sensitiveDataDenyListRegex, $key) === 1;
    }

    /**
     * @param string[] $terms
     */
    private static function matchesAnyTerm(string $key, array $terms, bool $partial): bool
    {
        $key = strtolower($key);

        foreach ($terms as $term) {
            $term = strtolower($term);
            if ($term === '') {
                continue;
            }
            if ($partial ? strpos($key, $term) !== false : $key === $term) {
                return true;
            }
        }

        return false;
    }
}
