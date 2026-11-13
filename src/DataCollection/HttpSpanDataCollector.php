<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Exception\JsonException;
use Sentry\Options;
use Sentry\Util\JSON;

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
    public static function collectServerRequest(Options $options, ServerRequestInterface $request): array
    {
        $type = HttpMessageType::incomingRequest();

        return self::formatHeaders($type, HttpHeaderCollector::collect($options, $type, $request->getHeaders()))
            + self::collectServerRequestCookies($options, $request)
            + self::formatBody($type, HttpBodyCollector::collectServerRequest($options, $request));
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectPsr7Request(Options $options, HttpMessageType $type, RequestInterface $request): array
    {
        return self::formatHeaders($type, HttpHeaderCollector::collect($options, $type, $request->getHeaders()))
            + self::collectCookieHeaders($options, $type, $request->getHeader('Cookie'))
            + self::formatBody($type, HttpBodyCollector::collectPsr7Message($options, $type, $request));
    }

    /**
     * @return array<string, mixed>
     */
    public static function collectPsr7Response(Options $options, HttpMessageType $type, ResponseInterface $response): array
    {
        return self::formatHeaders($type, HttpHeaderCollector::collect($options, $type, $response->getHeaders()))
            + self::collectCookieHeaders($options, $type, $response->getHeader('Set-Cookie'))
            + self::formatBody($type, HttpBodyCollector::collectPsr7Message($options, $type, $response));
    }

    /**
     * Collects the headers of an HTTP message that is not available as a PSR-7 message. Cookie headers
     * are not collected, use {@see collectCookieHeaders()} or {@see collectCookiePairs()} for them.
     *
     * @param array<array-key, string[]> $headers
     *
     * @return array<string, string>
     */
    public static function collectHeaders(Options $options, HttpMessageType $type, array $headers): array
    {
        return self::formatHeaders($type, HttpHeaderCollector::collect($options, $type, $headers));
    }

    /**
     * @param string[] $headers
     *
     * @return array<string, string[]>
     */
    public static function collectCookieHeaders(Options $options, HttpMessageType $type, array $headers): array
    {
        if (!self::shouldCollectCookies($options)) {
            return [];
        }

        return self::formatCookies($options, $type, self::splitCookieHeaders($type, $headers));
    }

    /**
     * @param array<int, array{string, mixed}> $cookies The cookie name and value pairs
     *
     * @return array<string, string[]>
     */
    public static function collectCookiePairs(Options $options, HttpMessageType $type, array $cookies): array
    {
        if (!self::shouldCollectCookies($options)) {
            return [];
        }

        return self::formatCookies($options, $type, $cookies);
    }

    /**
     * @param mixed $body The raw body, or the body that was already parsed
     *
     * @return array<string, string>
     */
    public static function collectBody(Options $options, HttpMessageType $type, $body, string $contentType = '', ?int $bodyLength = null): array
    {
        return self::formatBody($type, HttpBodyCollector::collect($options, $type, $body, $contentType, $bodyLength));
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
    private static function collectServerRequestCookies(Options $options, ServerRequestInterface $request): array
    {
        if (!self::shouldCollectCookies($options)) {
            return [];
        }

        $type = HttpMessageType::incomingRequest();
        $cookies = [];

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($request->getCookieParams() as $name => $value) {
            $cookies[] = [(string) $name, $value];
        }

        if ($cookies === []) {
            return self::formatCookies($options, $type, self::splitCookieHeaders($type, $request->getHeader('Cookie')));
        }

        return self::formatCookies($options, $type, $cookies);
    }

    private static function shouldCollectCookies(Options $options): bool
    {
        return !$options->getDataCollection()->getCookies()->isOff();
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
    private static function formatCookies(Options $options, HttpMessageType $type, ?array $cookies): array
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
        foreach (HttpCookieCollector::collectPairs($options, $cookies) ?? [] as [$name, $value]) {
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

        // Cleared cookies have no value, which must not look like a filtered value
        if ($value !== null && !\is_scalar($value)) {
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
            try {
                $body = JSON::encode($body);
            } catch (JsonException $exception) {
                $body = KeyValueDataFilter::FILTERED_VALUE;
            }
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
