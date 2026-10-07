<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

final class HttpCookieParser
{
    private function __construct()
    {
    }

    /**
     * @param string[] $headers
     *
     * @return array<int, array{string, string}>
     */
    public static function parseCookieHeaders(array $headers): array
    {
        $cookies = [];
        foreach ($headers as $header) {
            foreach (explode(';', $header) as $part) {
                $cookie = self::parsePair(trim($part));
                if ($cookie === null) {
                    continue;
                }

                $cookies[$cookie[0]] = $cookie;
            }
        }

        return array_values($cookies);
    }

    /**
     * @param string[] $headers
     *
     * @return array<int, array{string, string}>
     */
    public static function parseSetCookieHeaders(array $headers): array
    {
        $cookies = [];
        foreach ($headers as $header) {
            // Set-Cookies have path and other attributes so we only take the first segment
            $cookie = self::parsePair(explode(';', $header, 2)[0]);
            if ($cookie === null) {
                continue;
            }

            $cookies[] = [trim($cookie[0]), $cookie[1]];
        }

        return $cookies;
    }

    /**
     * Splits `Cookie` headers into name and value pairs, keeping their order and duplicate names.
     * A segment without `=` is a nameless cookie, its whole segment is the value and its name is empty.
     *
     * @param string[] $headers
     *
     * @return array<int, array{string, string}>
     */
    public static function splitCookieHeaders(array $headers): array
    {
        $cookies = [];
        foreach ($headers as $header) {
            foreach (explode(';', $header) as $segment) {
                $cookie = self::splitSegment($segment);
                if ($cookie !== null) {
                    $cookies[] = $cookie;
                }
            }
        }

        return $cookies;
    }

    /**
     * Splits `Set-Cookie` headers into name and value pairs, keeping their order and duplicate names. Each
     * header holds a single cookie, the attributes after the first `;` like `Path` or `HttpOnly` are dropped.
     *
     * @param string[] $headers
     *
     * @return array<int, array{string, string}>
     */
    public static function splitSetCookieHeaders(array $headers): array
    {
        $cookies = [];
        foreach ($headers as $header) {
            $cookie = self::splitSegment(explode(';', $header, 2)[0]);
            if ($cookie !== null) {
                $cookies[] = $cookie;
            }
        }

        return $cookies;
    }

    /**
     * @return array{string, string}|null `null` for empty segments
     */
    private static function splitSegment(string $segment): ?array
    {
        $segment = trim($segment);
        if ($segment === '' || $segment === '=') {
            return null;
        }

        $pair = explode('=', $segment, 2);
        if (\count($pair) === 1) {
            return ['', $segment];
        }

        return [trim($pair[0]), trim($pair[1])];
    }

    /**
     * @return array{string, string}|null
     */
    private static function parsePair(string $part): ?array
    {
        $pair = explode('=', $part, 2);
        if (\count($pair) !== 2 || trim($pair[0]) === '') {
            return null;
        }

        return [$pair[0], $pair[1]];
    }
}
