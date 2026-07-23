<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * @internal
 *
 * @phpstan-type KeyValueCollectionBehavior array{mode: 'off'|'denyList'|'allowList', terms: string[]}
 */
final class SensitiveDataScrubber
{
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
     * Headers in lowercase that are always scrubbed. The spec forbids sending
     * raw Cookie/Set-Cookie header values even in allow-list mode; individual
     * cookies are collected separately, subject to the cookies behavior.
     * IP-carrying headers (x-forwarded-for, ...) are deliberately absent: the
     * spec handles them through user-supplied extended deny terms instead.
     */
    private const SENSITIVE_HEADERS = [
        'cookie',
        'set-cookie',
    ];

    /**
     * @var string|null
     */
    private static $sensitiveDataDenyListRegex;

    /**
     * This class contains only static methods and should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return array<string, string[]>
     */
    public static function scrubHeaders(array $headers, array $behavior): array
    {
        $scrubbed = [];

        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (\in_array(strtolower($name), self::SENSITIVE_HEADERS, true) || self::shouldScrubValue($name, $behavior)) {
                foreach ($values as $headerLine => $headerValue) {
                    $values[$headerLine] = '[Filtered]';
                }
            }

            $scrubbed[$name] = $values;
        }

        return $scrubbed;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @phpstan-param KeyValueCollectionBehavior $behavior
     *
     * @return array<string, mixed>
     */
    public static function scrubKeyValueData(array $data, array $behavior): array
    {
        $scrubbed = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($data as $key => $value) {
            $key = (string) $key;
            $scrubbed[$key] = self::shouldScrubValue($key, $behavior) ? '[Filtered]' : $value;
        }

        return $scrubbed;
    }

    /**
     * @phpstan-param KeyValueCollectionBehavior $behavior
     */
    public static function scrubQueryString(string $queryString, array $behavior): string
    {
        $parts = explode('&', $queryString);

        foreach ($parts as $index => $part) {
            $separatorPosition = strpos($part, '=');
            $encodedKey = $separatorPosition === false ? $part : substr($part, 0, $separatorPosition);
            $key = urldecode($encodedKey);

            if (self::shouldScrubValue($key, $behavior)) {
                $parts[$index] = $encodedKey . '=[Filtered]';
            }
        }

        return implode('&', $parts);
    }

    /**
     * @phpstan-param KeyValueCollectionBehavior $behavior
     */
    private static function shouldScrubValue(string $key, array $behavior): bool
    {
        if (self::matchesMandatoryDenyList($key)) {
            return true;
        }

        if ($behavior['mode'] === 'allowList') {
            return !self::matchesAnyTerm($key, $behavior['terms'], false);
        }

        return $behavior['terms'] !== [] && self::matchesAnyTerm($key, $behavior['terms'], true);
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

            if (($partial && strpos($key, $term) !== false) || (!$partial && $key === $term)) {
                return true;
            }
        }

        return false;
    }
}
