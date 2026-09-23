<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
    public static function collect(DataCollectionPolicy $policy, HttpMessageType $type, ?array $cookies): ?array
    {
        if ($cookies === null) {
            return null;
        }

        $dataCollection = $policy->getDataCollection();
        if ($dataCollection === null) {
            return self::shouldCollectLegacyCookies($policy, $type) ? $cookies : null;
        }

        return (new KeyValueDataFilter($dataCollection->getCookies()))->filterKeyValueData($cookies);
    }

    /**
     * @return array<array-key, mixed>|string|null `null` if cookies are not collected, `[Filtered]` if they
     *                                             could not be parsed
     */
    public static function collectPsr7Request(DataCollectionPolicy $policy, HttpMessageType $type, RequestInterface $request)
    {
        if (!self::shouldCollect($policy, $type)) {
            return null;
        }

        return self::collectGroupedPairs($policy, $type, HttpCookieParser::parseCookieHeaders($request->getHeader('Cookie')));
    }

    /**
     * @return array<array-key, mixed>|string|null `null` if cookies are not collected, `[Filtered]` if they
     *                                             could not be parsed
     */
    public static function collectPsr7Response(DataCollectionPolicy $policy, HttpMessageType $type, ResponseInterface $response)
    {
        if (!self::shouldCollect($policy, $type)) {
            return null;
        }

        return self::collectGroupedPairs($policy, $type, HttpCookieParser::parseSetCookieHeaders($response->getHeader('Set-Cookie')));
    }

    /**
     * @param array<int, array{string, mixed}> $cookies
     *
     * @return array<int, array{string, mixed}>|null `null` if cookies are not collected
     */
    public static function collectPairs(DataCollectionPolicy $policy, HttpMessageType $type, array $cookies): ?array
    {
        $dataCollection = $policy->getDataCollection();
        if ($dataCollection === null) {
            if (!self::shouldCollectLegacyCookies($policy, $type)) {
                return null;
            }

            return $cookies;
        }

        return (new KeyValueDataFilter($dataCollection->getCookies()))->filterPairs($cookies);
    }

    /**
     * @param array<int, array{string, mixed}>|null $cookies
     *
     * @return array<array-key, mixed>|string|null `null` if cookies are not collected, `[Filtered]` if they
     *                                             could not be parsed
     */
    public static function collectGroupedPairs(DataCollectionPolicy $policy, HttpMessageType $type, ?array $cookies)
    {
        $filtered = self::collectPairs($policy, $type, $cookies ?? []);
        if ($filtered === null) {
            return null;
        }

        if ($cookies === null) {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        $grouped = [];
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($filtered as [$name, $value]) {
            $grouped[$name][] = $value;
        }

        foreach ($grouped as $name => $values) {
            $grouped[$name] = \count($values) === 1 ? $values[0] : $values;
        }

        return $grouped;
    }

    private static function shouldCollect(DataCollectionPolicy $policy, HttpMessageType $type): bool
    {
        $dataCollection = $policy->getDataCollection();
        if ($dataCollection === null) {
            return self::shouldCollectLegacyCookies($policy, $type);
        }

        return !$dataCollection->getCookies()->isOff();
    }

    /**
     * The legacy options only collected the cookies of incoming requests, and only with `send_default_pii`.
     */
    private static function shouldCollectLegacyCookies(DataCollectionPolicy $policy, HttpMessageType $type): bool
    {
        return $type === HttpMessageType::incomingRequest() && $policy->shouldCollectUserInfo();
    }
}
