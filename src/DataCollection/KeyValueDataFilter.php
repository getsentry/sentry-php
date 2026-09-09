<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * @phpstan-type KeyValueCollectionBehavior array{mode: 'off'|'denyList'|'allowList', terms: string[]}
 */
final class KeyValueDataFilter
{
    public const FILTERED_VALUE = '[Filtered]';

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

    /**
     * Cookie headers are collected separately as cookie data.
     */
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
     * @return array<string, string[]>|null Returns null when collection is off
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

            if (self::shouldFilterValue($name, $behavior)) {
                foreach ($values as $headerLine => $headerValue) {
                    $values[$headerLine] = self::FILTERED_VALUE;
                }
            }

            $filtered[$name] = $values;
        }

        return $filtered;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return array<string, mixed>|null Returns null when collection is off
     */
    public static function filterKeyValueData(array $data, array $behavior): ?array
    {
        if ($behavior['mode'] === 'off') {
            return null;
        }

        $filtered = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($data as $key => $value) {
            $key = (string) $key;

            if (self::shouldFilterValue($key, $behavior)) {
                $filtered[$key] = self::FILTERED_VALUE;
            } elseif (\is_array($value)) {
                $filtered[$key] = self::filterKeyValueData($value, $behavior);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Applies cookie policy to names, preserving all values of a repeated cookie.
     *
     * @param array<array-key, mixed> $cookies
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return array<array-key, mixed>|null
     */
    public static function filterCookies(array $cookies, array $behavior): ?array
    {
        if ($behavior['mode'] === 'off') {
            return null;
        }

        $filtered = [];
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($cookies as $name => $value) {
            $filtered[$name] = self::shouldFilterValue((string) $name, $behavior) ? self::FILTERED_VALUE : $value;
        }

        return $filtered;
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
            $encodedKey = $separatorPosition === false ? $part : substr($part, 0, $separatorPosition);
            $key = urldecode($encodedKey);

            if ($separatorPosition !== false && self::shouldFilterValue($key, $behavior)) {
                $parts[$index] = $encodedKey . '=' . self::FILTERED_VALUE;
            }
        }

        return implode('&', $parts);
    }

    /**
     * @phpstan-param KeyValueCollectionBehavior $behavior
     */
    private static function shouldFilterValue(string $key, array $behavior): bool
    {
        if (self::matchesMandatoryDenyList($key)) {
            return true;
        }

        if ($behavior['mode'] === 'allowList') {
            return !self::matchesAnyTerm($key, $behavior['terms']);
        }

        return self::matchesAnyTerm($key, $behavior['terms']);
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
     * Only the mandatory sensitive denylist uses substring matching.
     *
     * @param string[] $terms
     */
    private static function matchesAnyTerm(string $key, array $terms): bool
    {
        $key = strtolower($key);

        foreach ($terms as $term) {
            if ($key === strtolower($term)) {
                return true;
            }
        }

        return false;
    }
}
