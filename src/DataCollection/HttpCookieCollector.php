<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

final class HttpCookieCollector
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $cookies PHP/PSR-7 cookie parameters
     *
     * @return array<array-key, mixed>|null
     */
    public static function collect(DataCollectionOptions $options, array $cookies): ?array
    {
        return KeyValueDataFilter::filterKeyValueData($cookies, $options->getCookies());
    }

    /**
     * @param iterable<array{string, mixed}> $cookies Parsed cookie name/value pairs
     *
     * @return array<int, array{string, mixed}>|null
     */
    public static function collectPairs(DataCollectionOptions $options, iterable $cookies): ?array
    {
        $behavior = $options->getCookies();
        if ($behavior['mode'] === 'off') {
            return null;
        }

        $filtered = [];
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($cookies as [$name, $value]) {
            $filtered[] = [$name, KeyValueDataFilter::filterKeyValue($name, $value, $behavior)];
        }

        return $filtered;
    }
}
