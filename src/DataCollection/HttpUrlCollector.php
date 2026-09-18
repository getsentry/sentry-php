<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Uri;

/**
 * @internal
 */
final class HttpUrlCollector
{
    private function __construct()
    {
    }

    public static function collect(DataCollectionPolicy $policy, string $url): string
    {
        if ($policy->isLegacyMode()) {
            return $url;
        }

        $uri = new Uri($url);
        $query = self::collectQueryString($policy, (string) parse_url($url, \PHP_URL_QUERY));
        $result = (string) $uri->withUserInfo('')->withQuery('')->withFragment('');

        if ($query !== null && $query !== '') {
            $result .= '?' . $query;
        }

        return $result;
    }

    public static function collectQueryString(DataCollectionPolicy $policy, string $queryString): ?string
    {
        if ($queryString === '') {
            return null;
        }

        $collection = $policy->getDataCollection();

        return $collection === null
            ? $queryString
            : KeyValueDataFilter::filterQueryString($queryString, $collection->getUrlQueryParams());
    }
}
