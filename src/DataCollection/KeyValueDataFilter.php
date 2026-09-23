<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * Applies a {@see KeyValueCollectionBehavior} together with the sensitive deny list to key-value data.
 * Every filter method returns `null` if the category is not collected.
 */
final class KeyValueDataFilter
{
    public const FILTERED_VALUE = '[Filtered]';

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

    /**
     * @var KeyValueCollectionBehavior
     */
    private $behavior;

    public function __construct(KeyValueCollectionBehavior $behavior)
    {
        $this->behavior = $behavior;
    }

    /**
     * Whether the category is collected at all.
     */
    public function isEnabled(): bool
    {
        return !$this->behavior->isOff();
    }

    /**
     * @template TKey of array-key
     *
     * @param array<TKey, mixed>            $data
     * @param (callable(mixed): mixed)|null $serializeValue Converts top-level values before they are filtered. It is
     *                                                      only called for keys that are not filtered, so sensitive
     *                                                      values are never passed to it.
     *
     * @return array<TKey, mixed>|null
     */
    public function filterKeyValueData(array $data, ?callable $serializeValue = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $filtered = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($data as $key => $value) {
            $name = (string) $key;

            if ($serializeValue !== null && !$this->shouldFilter($name)) {
                /** @mago-ignore analysis:mixed-assignment */
                $value = $serializeValue($value);
            }

            $filtered[$key] = $this->filterValue($name, $value);
        }

        return $filtered;
    }

    /**
     * @param array<int, array{string, mixed}> $pairs
     *
     * @return array<int, array{string, mixed}>|null
     */
    public function filterPairs(array $pairs): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $filtered = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($pairs as [$name, $value]) {
            $filtered[] = [$name, $this->filterValue($name, $value)];
        }

        return $filtered;
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<array-key, string[]>|null
     */
    public function filterHeaders(array $headers): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $filtered = [];

        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (\in_array(strtolower($name), self::EXCLUDED_HEADERS, true)) {
                continue;
            }

            $shouldFilter = $this->shouldFilter($name);
            $filtered[$name] = [];

            foreach ($values as $headerLine => $headerValue) {
                $filtered[$name][$headerLine] = $shouldFilter ? self::FILTERED_VALUE : $headerValue;
            }
        }

        return $filtered;
    }

    /**
     * Filters the values of a raw query string while preserving its encoding.
     */
    public function filterQueryString(string $queryString): ?string
    {
        if (!$this->isEnabled()) {
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

            if ($this->shouldFilter($key)) {
                $parts[$index] = $encodedKey . '=' . self::FILTERED_VALUE;
            }
        }

        return implode('&', $parts);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function filterValue(string $key, $value)
    {
        if (\PHP_VERSION_ID >= 70400) {
            return $this->filterValueWithCycleDetection($key, $value, 1);
        }

        return $this->filterValueWithDepthLimit($key, $value, 1);
    }

    /**
     * @param mixed               $value
     * @param array<string, true> $references References on the current recursion path
     *
     * @return mixed
     */
    private function filterValueWithCycleDetection(string $key, $value, int $depth, array $references = [])
    {
        if ($depth > self::MAX_DEPTH || $this->shouldFilter($key)) {
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

                $filtered[$childKey] = $this->filterValueWithCycleDetection((string) $childKey, $childValue, $depth + 1, $childReferences);
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
     * @return mixed
     */
    private function filterValueWithDepthLimit(string $key, $value, int $depth)
    {
        if ($depth > self::MAX_DEPTH || $this->shouldFilter($key)) {
            return self::FILTERED_VALUE;
        }

        if (\is_array($value)) {
            $filtered = [];

            /** @mago-ignore analysis:mixed-assignment */
            foreach ($value as $childKey => $childValue) {
                $filtered[$childKey] = $this->filterValueWithDepthLimit((string) $childKey, $childValue, $depth + 1);
            }

            return $filtered;
        }

        if (($value !== null && !\is_scalar($value)) || (\is_float($value) && !is_finite($value))) {
            return self::FILTERED_VALUE;
        }

        return $value;
    }

    private function shouldFilter(string $key): bool
    {
        if (self::matchesMandatoryDenyList($key)) {
            return true;
        }

        if ($this->behavior->getMode() === KeyValueCollectionBehavior::MODE_ALLOW_LIST) {
            return !self::matchesAnyTerm($key, $this->behavior->getTerms(), false);
        }

        return self::matchesAnyTerm($key, $this->behavior->getTerms(), true);
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
