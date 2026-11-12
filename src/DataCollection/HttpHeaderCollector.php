<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;

final class HttpHeaderCollector
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, string[]>|null $headers
     * @param string[]|null                   $piiSanitizeHeaders
     *
     * @return array<array-key, string[]>|null `null` if headers are not collected or not available
     */
    public static function collect(Options $options, HttpMessageType $type, ?array $headers, ?array $piiSanitizeHeaders = null): ?array
    {
        if ($headers === null) {
            return null;
        }

        $httpHeaders = $options->getDataCollection()->getHttpHeaders();
        $behavior = $type->isRequest() ? $httpHeaders['request'] : $httpHeaders['response'];
        $filtered = (new KeyValueDataFilter($behavior))->filterHeaders($headers);

        if ($filtered === null) {
            return null;
        }

        $filtered = self::filterRefererUrls($options, $type, $filtered);

        if ($piiSanitizeHeaders === null) {
            return $filtered;
        }

        return self::sanitize($filtered, $piiSanitizeHeaders);
    }

    /**
     * The `Referer` header contains a URL, so it is collected like any other URL to apply the query param collection behavior.
     *
     * @param array<array-key, string[]> $headers
     *
     * @return array<array-key, string[]>
     */
    private static function filterRefererUrls(Options $options, HttpMessageType $type, array $headers): array
    {
        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) !== 'referer') {
                continue;
            }

            foreach ($values as $headerLine => $headerValue) {
                if ($headerValue !== KeyValueDataFilter::FILTERED_VALUE) {
                    $headers[$name][$headerLine] = HttpUrlCollector::collect($options, $type, $headerValue) ?? KeyValueDataFilter::FILTERED_VALUE;
                }
            }
        }

        return $headers;
    }

    /**
     * @param array<array-key, string[]> $headers
     * @param string[]                   $names
     *
     * @return array<array-key, string[]>
     */
    private static function sanitize(array $headers, array $names): array
    {
        $names = array_map('strtolower', $names);
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $shouldSanitize = \in_array(strtolower((string) $name), $names, true);
            $sanitized[$name] = [];

            foreach ($values as $headerLine => $headerValue) {
                $sanitized[$name][$headerLine] = $shouldSanitize ? KeyValueDataFilter::FILTERED_VALUE : $headerValue;
            }
        }

        return $sanitized;
    }
}
