<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Uri;
use Sentry\Tracing\Span;

/**
 * Collects transport-independent HTTP data. Integrations provide normalized
 * inputs without consuming streams or invoking application callbacks.
 *
 * @internal
 */
final class HttpDataCollector
{
    private function __construct()
    {
    }

    public static function collectQueryString(?DataCollectionOptions $dataCollection, string $queryString): ?string
    {
        if ($queryString === '') {
            return null;
        }

        return $dataCollection === null
            ? $queryString
            : KeyValueDataFilter::filterQueryString($queryString, $dataCollection->getUrlQueryParams());
    }

    public static function collectUrl(?DataCollectionOptions $dataCollection, string $url): string
    {
        if ($dataCollection === null) {
            return $url;
        }

        $uri = new Uri($url);
        $query = self::collectQueryString($dataCollection, (string) parse_url($url, \PHP_URL_QUERY));
        $result = (string) $uri->withUserInfo('')->withQuery('')->withFragment('');

        if ($query !== null && $query !== '') {
            $result .= '?' . $query;
        }

        if ($uri->getFragment() !== '') {
            $result .= '#' . $uri->getFragment();
        }

        return $result;
    }

    /**
     * @param array<array-key, string[]> $headers
     * @param 'request'|'response'       $direction
     *
     * @return array<string, string[]>
     */
    public static function collectHeaders(DataCollectionOptions $dataCollection, array $headers, string $direction): array
    {
        $headerBehavior = $dataCollection->getHttpHeaders()[$direction];
        $cookieBehavior = $dataCollection->getCookies();
        $prefix = 'http.' . $direction . '.header.';
        $regularHeaders = [];
        $attributes = [];

        foreach ($headers as $name => $values) {
            $name = strtolower((string) $name);

            if ($name === 'cookie' || $name === 'set-cookie') {
                if ($cookieBehavior['mode'] !== 'off' && $values !== []) {
                    // Raw cookie headers cannot be filtered by individual cookie name.
                    $attributes[$prefix . $name] = array_fill(0, \count($values), KeyValueDataFilter::FILTERED_VALUE);
                }

                continue;
            }

            $regularHeaders[$name] = $values;
        }

        $filteredHeaders = KeyValueDataFilter::filterHeaders($regularHeaders, $headerBehavior);
        foreach ($filteredHeaders ?? [] as $name => $values) {
            $attributes[$prefix . $name] = $values;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function setMissingSpanData(Span $span, array $data): void
    {
        $span->setData(array_diff_key($data, $span->getData()));
    }
}
