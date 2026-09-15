<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
    public static function collectBodyData(DataCollectionPolicy $policy, string $bodyType, $body, string $contentType = ''): array
    {
        return self::bodyDataToAttribute($bodyType, HttpBodyCollector::collect($policy, $bodyType, $body, $contentType));
    }

    /**
     * Collects body data from a PSR-7 message without consuming its stream.
     *
     * @return array<string, mixed>
     */
    public static function collectPsr7BodyData(DataCollectionPolicy $policy, string $bodyType, MessageInterface $message): array
    {
        return self::bodyDataToAttribute($bodyType, HttpBodyCollector::collectPsr7Message($policy, $bodyType, $message));
    }

    /**
     * @param mixed $body
     *
     * @return array<string, mixed>
     */
    private static function bodyDataToAttribute(string $bodyType, $body): array
    {
        $direction = $bodyType === DataCollectionOptions::HTTP_BODY_INCOMING_REQUEST || $bodyType === DataCollectionOptions::HTTP_BODY_OUTGOING_REQUEST ? 'request' : 'response';

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
    public static function collectQueryData(DataCollectionPolicy $policy, string $queryString): array
    {
        $queryString = self::collectQueryString($policy, $queryString);

        return $queryString === null ? [] : ['http.query' => $queryString];
    }

    public static function collectQueryString(DataCollectionPolicy $policy, string $queryString): ?string
    {
        if ($queryString === '') {
            return null;
        }

        $dataCollection = $policy->getDataCollection();

        return $dataCollection === null
            ? $queryString
            : KeyValueDataFilter::filterQueryString($queryString, $dataCollection->getUrlQueryParams());
    }

    public static function collectUrl(DataCollectionPolicy $policy, string $url, ?string $legacyUrl = null): string
    {
        if ($policy->isLegacyMode()) {
            return $legacyUrl ?? $url;
        }

        $uri = new Uri($url);
        $query = self::collectQueryString($policy, (string) parse_url($url, \PHP_URL_QUERY));
        $result = (string) $uri->withUserInfo('')->withQuery('')->withFragment('');

        if ($query !== null && $query !== '') {
            $result .= '?' . $query;
        }

        return $result;
    }

    public static function shouldCollectRequestHeadersOrCookies(DataCollectionPolicy $policy): bool
    {
        $dataCollection = $policy->getDataCollection();

        return $dataCollection !== null
            && ($dataCollection->getHttpHeaders()['request']['mode'] !== 'off' || $dataCollection->getCookies()['mode'] !== 'off');
    }

    public static function shouldCollectResponseHeadersOrCookies(DataCollectionPolicy $policy): bool
    {
        $dataCollection = $policy->getDataCollection();

        return $dataCollection !== null
            && ($dataCollection->getHttpHeaders()['response']['mode'] !== 'off' || $dataCollection->getCookies()['mode'] !== 'off');
    }

    /**
     * Collects headers and cookies from a PSR-7 request when enabled.
     *
     * @return array<string, mixed>
     */
    public static function collectPsr7RequestData(DataCollectionPolicy $policy, RequestInterface $request): array
    {
        if (!self::shouldCollectRequestHeadersOrCookies($policy)) {
            return [];
        }

        return self::collectRequestData($policy, HttpHeaderNormalizer::normalize($request->getHeaders()));
    }

    /**
     * Collects headers and cookies from a PSR-7 response when enabled.
     *
     * @return array<string, mixed>
     */
    public static function collectPsr7ResponseData(DataCollectionPolicy $policy, ResponseInterface $response): array
    {
        if (!self::shouldCollectResponseHeadersOrCookies($policy)) {
            return [];
        }

        return self::collectResponseData($policy, HttpHeaderNormalizer::normalize($response->getHeaders()));
    }

    /**
     * Collects HTTP request headers and cookies.
     *
     * @param array<array-key, string[]>   $headers Normalized lowercase header names
     * @param array<array-key, mixed>|null $cookies Parsed cookies
     *
     * @return array<string, mixed>
     */
    public static function collectRequestData(DataCollectionPolicy $policy, array $headers, ?array $cookies = null): array
    {
        $dataCollection = $policy->getDataCollection();
        if ($dataCollection === null) {
            return [];
        }

        $data = self::collectRequestHeaders($dataCollection, $headers);
        if ($dataCollection->getCookies()['mode'] !== 'off') {
            $malformed = false;
            $parsedCookies = self::parseCookies($headers['cookie'] ?? [], false, $malformed);
            $data = array_merge($data, self::collectRequestCookies($dataCollection, $cookies ?? $parsedCookies));
            if ($malformed) {
                $data['http.request.header.cookie'] = KeyValueDataFilter::FILTERED_VALUE;
            }
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
    public static function collectResponseData(DataCollectionPolicy $policy, array $headers, ?iterable $cookies = null): array
    {
        $dataCollection = $policy->getDataCollection();
        if ($dataCollection === null) {
            return [];
        }

        $data = self::collectResponseHeaders($dataCollection, $headers);
        if ($dataCollection->getCookies()['mode'] !== 'off') {
            $malformed = false;
            $parsedCookies = self::parseCookies($headers['set-cookie'] ?? [], true, $malformed);
            $cookies = $cookies === null ? $parsedCookies : self::groupCookieValues($cookies);
            $data = array_merge($data, self::collectResponseCookies($dataCollection, $cookies));
            if ($malformed) {
                $data['http.response.header.set_cookie'] = KeyValueDataFilter::FILTERED_VALUE;
            }
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
    public static function parseRequestCookies(array $headers, ?bool &$malformed = null): array
    {
        return self::parseCookies($headers, false, $malformed);
    }

    /**
     * @param string[] $headers Set-Cookie header values
     *
     * @return array<string, string|string[]>
     */
    public static function parseResponseCookies(array $headers, ?bool &$malformed = null): array
    {
        return self::parseCookies($headers, true, $malformed);
    }

    /**
     * @param string[] $headers
     *
     * @return array<string, string|string[]>
     */
    private static function parseCookies(array $headers, bool $response, ?bool &$malformed = null): array
    {
        $malformed = false;
        $pairs = [];
        foreach ($headers as $header) {
            $parts = $response ? [explode(';', $header, 2)[0]] : explode(';', $header);
            foreach ($parts as $part) {
                $pair = explode('=', $part, 2);
                if (\count($pair) !== 2 || trim($pair[0]) === '') {
                    $malformed = true;
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
}
