<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;

final class HttpCookieCollector
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed>|null $cookies
     *
     * @return array<array-key, mixed>|null `null` if cookies are not collected or not available
     */
    public static function collect(Options $options, ?array $cookies): ?array
    {
        if ($cookies === null) {
            return null;
        }

        return (new KeyValueDataFilter($options->getDataCollection()->getCookies()))->filterKeyValueData($cookies);
    }

    /**
     * @param array<int, array{string, mixed}> $cookies
     *
     * @return array<int, array{string, mixed}>|null `null` if cookies are not collected
     */
    public static function collectPairs(Options $options, array $cookies): ?array
    {
        return (new KeyValueDataFilter($options->getDataCollection()->getCookies()))->filterPairs($cookies);
    }
}
