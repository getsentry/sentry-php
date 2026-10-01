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
