<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Collects request and response data (cookies, headers, body) and returns them as array
 * with the proper span attribute names.
 */
final class HttpSpanDataCollector
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectServerRequest(DataCollectionPolicy $policy, ServerRequestInterface $request): array
    {
        if ($policy->isLegacyMode()) {
            return [];
        }

        $type = HttpMessageType::incomingRequest();

        return self::formatHeaders($type, HttpHeaderCollector::collect($policy, $type, $request->getHeaders()))
            + self::collectServerRequestCookies($policy, $request)
            + self::formatBody($type, HttpBodyCollector::collectServerRequest($policy, $request));
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectPsr7Request(DataCollectionPolicy $policy, HttpMessageType $type, RequestInterface $request): array
    {
        if ($policy->isLegacyMode()) {
            return [];
        }

        return self::formatHeaders($type, HttpHeaderCollector::collect($policy, $type, $request->getHeaders()))
            + self::collectCookieHeaders($policy, $type, $request->getHeader('Cookie'))
            + self::formatBody($type, HttpBodyCollector::collectPsr7Message($policy, $type, $request));
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectPsr7Response(DataCollectionPolicy $policy, HttpMessageType $type, ResponseInterface $response): array
    {
        if ($policy->isLegacyMode()) {
            return [];
        }

        return self::formatHeaders($type, HttpHeaderCollector::collect($policy, $type, $response->getHeaders()))
            + self::collectCookieHeaders($policy, $type, $response->getHeader('Set-Cookie'))
            + self::formatBody($type, HttpBodyCollector::collectPsr7Message($policy, $type, $response));
    }

    /**
     * Collects the headers of an HTTP message that is not available as a PSR-7 message. Cookie headers
     * are not collected, use {@see collectCookieHeaders()} or {@see collectCookiePairs()} for them.
     *
     * @param array<array-key, string[]> $headers
     *
     * @return array<string, string>
     */
    public static function collectHeaders(DataCollectionPolicy $policy, HttpMessageType $type, array $headers): array
    {
        if ($policy->isLegacyMode()) {
            return [];
        }

        return self::formatHeaders($type, HttpHeaderCollector::collect($policy, $type, $headers));
    }

    /**
     * @param string[] $headers
     *
     * @return array<string, string[]>
     */
    public static function collectCookieHeaders(DataCollectionPolicy $policy, HttpMessageType $type, array $headers): array
    {
        if (!self::shouldCollectCookies($policy)) {
            return [];
        }

        return self::formatCookies($policy, $type, self::splitCookieHeaders($type, $headers));
    }

    /**
     * @param array<int, array{string, mixed}> $cookies The cookie name and value pairs
     *
     * @return array<string, string[]>
     */
    public static function collectCookiePairs(DataCollectionPolicy $policy, HttpMessageType $type, array $cookies): array
    {
        if (!self::shouldCollectCookies($policy)) {
            return [];
        }

        return self::formatCookies($policy, $type, $cookies);
    }

    /**
     * @param mixed $body The raw body, or the body that was already parsed
     *
     * @return array<string, string>
     */
    public static function collectBody(DataCollectionPolicy $policy, HttpMessageType $type, $body, string $contentType = '', ?int $bodyLength = null): array
    {
        if ($policy->isLegacyMode()) {
            return [];
        }

        return self::formatBody($type, HttpBodyCollector::collect($policy, $type, $body, $contentType, $bodyLength));
    }

    /**
     * @param array<array-key, string[]>|null $headers
     *
     * @return array<string, string>
     */
    private static function formatHeaders(HttpMessageType $type, ?array $headers): array
    {
        $data = [];

        foreach ($headers ?? [] as $name => $values) {
            $data[self::getPrefix($type) . '.header.' . strtolower((string) $name)] = implode(', ', $values);
        }

        return $data;
    }

    /**
     * @return array<string, string[]>
     */
    private static function collectServerRequestCookies(DataCollectionPolicy $policy, ServerRequestInterface $request): array
    {
        if (!self::shouldCollectCookies($policy)) {
            return [];
        }

        $type = HttpMessageType::incomingRequest();
        $cookies = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($request->getCookieParams() as $name => $value) {
            $cookies[] = [(string) $name, $value];
        }

        if ($cookies === []) {
            return self::formatCookies($policy, $type, self::splitCookieHeaders($type, $request->getHeader('Cookie')));
        }

        return self::formatCookies($policy, $type, $cookies);
    }

    private static function shouldCollectCookies(DataCollectionPolicy $policy): bool
    {
        $dataCollection = $policy->getDataCollection();

        return $dataCollection !== null && !$dataCollection->getCookies()->isOff();
    }

    /**
     * @param string[] $headers
     *
     * @return array<int, array{string, string}>|null `null` if the headers hold no cookie
     */
    private static function splitCookieHeaders(HttpMessageType $type, array $headers): ?array
    {
        $headers = array_filter($headers, static function (string $header): bool {
            return trim($header) !== '';
        });

        if ($headers === []) {
            return [];
        }

        $cookies = $type->isRequest() ? HttpCookieParser::splitCookieHeaders($headers) : HttpCookieParser::splitSetCookieHeaders($headers);

        return $cookies === [] ? null : $cookies;
    }

    /**
     * Formats the cookies as `name=value` values of a single attribute, in the order they were received.
     *
     * @param array<int, array{string, mixed}>|null $cookies
     *
     * @return array<string, string[]>
     */
    private static function formatCookies(DataCollectionPolicy $policy, HttpMessageType $type, ?array $cookies): array
    {
        if ($cookies === []) {
            return [];
        }

        $key = $type->isRequest() ? 'http.request.header.cookie' : 'http.response.header.set-cookie';

        if ($cookies === null) {
            return [$key => [KeyValueDataFilter::FILTERED_VALUE]];
        }

        $values = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach (HttpCookieCollector::collectPairs($policy, $type, $cookies) ?? [] as [$name, $value]) {
            $values[] = self::formatCookie($name, $value);
        }

        return [$key => $values];
    }

    /**
     * @param mixed $value
     */
    private static function formatCookie(string $name, $value): string
    {
        if ($name === '') {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        if (!\is_scalar($value)) {
            return $name . '=' . KeyValueDataFilter::FILTERED_VALUE;
        }

        return $name . '=' . (string) $value;
    }

    /**
     * @param mixed $body
     *
     * @return array<string, string>
     */
    private static function formatBody(HttpMessageType $type, $body): array
    {
        if (\is_array($body)) {
            $body = json_encode($body) ?: KeyValueDataFilter::FILTERED_VALUE;
        }

        if (!\is_string($body)) {
            return [];
        }

        return [self::getPrefix($type) . '.body.data' => $body];
    }

    private static function getPrefix(HttpMessageType $type): string
    {
        return $type->isRequest() ? 'http.request' : 'http.response';
    }
}
