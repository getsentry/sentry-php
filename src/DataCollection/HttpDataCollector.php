<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Uri;
use Sentry\Options;
use Sentry\Tracing\Span;

/**
 * Collects transport-independent HTTP data. Integrations provide normalized
 * inputs without consuming streams or invoking application callbacks.
 */
final class HttpDataCollector
{
    /**
     * @param mixed $body
     *
     * @return array<string, mixed>
     */
    public static function collectBodyData(?Options $options, string $bodyType, $body, string $contentType = ''): array
    {
        $body = HttpBodyCollector::collect($options, $bodyType, $body, $contentType);
        $direction = $bodyType === 'incomingRequest' || $bodyType === 'outgoingRequest' ? 'request' : 'response';

        return $body === null ? [] : ['http.' . $direction . '.body.data' => $body];
    }

    private function __construct()
    {
    }

    /**
     * Collects the HTTP query attribute for spans and breadcrumbs.
     *
     * @return array<string, string>
     */
    public static function collectQueryData(?DataCollectionOptions $dataCollection, string $queryString): array
    {
        $queryString = self::collectQueryString($dataCollection, $queryString);

        return $queryString === null ? [] : ['http.query' => $queryString];
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

    public static function collectUrl(?DataCollectionOptions $dataCollection, string $url, ?string $legacyUrl = null): string
    {
        if ($dataCollection === null) {
            return $legacyUrl ?? $url;
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
     * Collects HTTP request headers and cookies.
     *
     * @param array<array-key, string[]>   $headers Normalized lowercase header names
     * @param array<array-key, mixed>|null $cookies Parsed cookies
     *
     * @return array<string, mixed>
     */
    public static function collectRequestData(?DataCollectionOptions $dataCollection, array $headers, ?array $cookies = null): array
    {
        if ($dataCollection === null) {
            return [];
        }

        $data = self::collectRequestHeaders($dataCollection, $headers);
        if ($dataCollection->getCookies()['mode'] !== 'off') {
            $cookies = $cookies ?? self::parseRequestCookies($headers['cookie'] ?? []);
            $data = array_merge($data, self::collectRequestCookies($dataCollection, $cookies));
        }

        return $data;
    }

    /**
     * Collects HTTP response attributes from normalized inputs.
     *
     * @param array<array-key, string[]>                $headers Normalized lowercase header names
     * @param iterable<array{string, string|null}>|null $cookies Parsed cookie name/value pairs
     *
     * @return array<string, mixed>
     */
    public static function collectResponseData(?DataCollectionOptions $dataCollection, array $headers, ?iterable $cookies = null): array
    {
        if ($dataCollection === null) {
            return [];
        }

        $data = self::collectResponseHeaders($dataCollection, $headers);
        if ($dataCollection->getCookies()['mode'] !== 'off') {
            $cookies = $cookies === null
                ? self::parseResponseCookies($headers['set-cookie'] ?? [])
                : self::groupCookieValues($cookies);
            $data = array_merge($data, self::collectResponseCookies($dataCollection, $cookies));
        }

        return $data;
    }

    /**
     * @param array<array-key, string[]> $headers Normalized lowercase header names
     *
     * @return array<string, string[]>
     */
    public static function collectRequestHeaders(DataCollectionOptions $dataCollection, array $headers): array
    {
        return self::collectHeaders($dataCollection, $headers, 'request');
    }

    /**
     * @param array<array-key, string[]> $headers Normalized lowercase header names
     *
     * @return array<string, string[]>
     */
    public static function collectResponseHeaders(DataCollectionOptions $dataCollection, array $headers): array
    {
        return self::collectHeaders($dataCollection, $headers, 'response');
    }

    /**
     * Collects regular headers, excluding Cookie and Set-Cookie.
     *
     * @param array<array-key, string[]> $headers
     * @param 'request'|'response'       $direction
     *
     * @return array<string, string[]>
     */
    private static function collectHeaders(DataCollectionOptions $dataCollection, array $headers, string $direction): array
    {
        $headerBehavior = $dataCollection->getHttpHeaders()[$direction];
        $prefix = 'http.' . $direction . '.header.';
        $attributes = [];

        $filteredHeaders = KeyValueDataFilter::filterHeaders($headers, $headerBehavior);
        foreach ($filteredHeaders ?? [] as $name => $values) {
            if ($values !== []) {
                $attributes[$prefix . $name] = $values;
            }
        }

        return $attributes;
    }

    /**
     * @param array<array-key, mixed> $cookies
     *
     * @return array<string, mixed>
     */
    public static function collectRequestCookies(DataCollectionOptions $dataCollection, array $cookies): array
    {
        return self::collectCookies($dataCollection, $cookies, 'request');
    }

    /**
     * @param array<array-key, mixed> $cookies
     *
     * @return array<string, mixed>
     */
    public static function collectResponseCookies(DataCollectionOptions $dataCollection, array $cookies): array
    {
        return self::collectCookies($dataCollection, $cookies, 'response');
    }

    /**
     * Collects parsed cookies by name, independently of regular headers.
     *
     * @param array<array-key, mixed> $cookies
     * @param 'request'|'response'    $direction
     *
     * @return array<string, mixed>
     */
    private static function collectCookies(DataCollectionOptions $dataCollection, array $cookies, string $direction): array
    {
        $filtered = KeyValueDataFilter::filterCookies($cookies, $dataCollection->getCookies());
        $prefix = $direction === 'request' ? 'http.request.header.cookie.' : 'http.response.header.set_cookie.';
        $attributes = [];
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($filtered ?? [] as $name => $value) {
            $attributes[$prefix . $name] = $value;
        }

        return $attributes;
    }

    /**
     * @param string[] $headers Cookie header values
     *
     * @return array<string, string|string[]>
     */
    public static function parseRequestCookies(array $headers): array
    {
        return self::parseCookies($headers, false);
    }

    /**
     * @param string[] $headers Set-Cookie header values
     *
     * @return array<string, string|string[]>
     */
    public static function parseResponseCookies(array $headers): array
    {
        return self::parseCookies($headers, true);
    }

    /**
     * @param string[] $headers
     *
     * @return array<string, string|string[]>
     */
    private static function parseCookies(array $headers, bool $response): array
    {
        $pairs = [];
        foreach ($headers as $header) {
            $parts = $response ? [explode(';', $header, 2)[0]] : explode(';', $header);
            foreach ($parts as $part) {
                $pair = explode('=', $part, 2);
                if (\count($pair) !== 2 || trim($pair[0]) === '') {
                    continue;
                }
                $pairs[] = [trim($pair[0]), trim($pair[1])];
            }
        }

        return self::groupCookieValues($pairs);
    }

    /**
     * Groups framework cookie name/value pairs without serializing cookie objects.
     *
     * @template T of string|null
     *
     * @param iterable<array{string, T}> $cookies
     *
     * @return array<string, T|T[]>
     */
    public static function groupCookieValues(iterable $cookies): array
    {
        /** @var array<string, T|T[]> $grouped */
        $grouped = [];
        foreach ($cookies as [$name, $value]) {
            if (\array_key_exists($name, $grouped)) {
                $previous = $grouped[$name];
                $values = \is_array($previous) ? $previous : [$previous];
                $values[] = $value;
                $grouped[$name] = $values;
            } else {
                $grouped[$name] = $value;
            }
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function setMissingSpanData(Span $span, array $data): void
    {
        $span->setData(array_diff_key($data, $span->getData()));
    }
}
