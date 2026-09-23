<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

final class HttpHeaderCollector
{
    /**
     * Headers sanitized in legacy mode when `send_default_pii` is disabled and no headers are configured explicitly.
     */
    public const DEFAULT_PII_SANITIZE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-forwarded-for',
        'x-real-ip',
    ];

    private function __construct()
    {
    }

    /**
     * @param array<array-key, string[]> $headers
     * @param string[]|null              $piiSanitizeHeaders
     *
     * @return array<array-key, string[]>|null `null` if headers are not collected
     */
    public static function collect(DataCollectionPolicy $policy, HttpMessageType $type, array $headers, ?array $piiSanitizeHeaders = null): ?array
    {
        $dataCollection = $policy->getDataCollection();
        if ($dataCollection === null) {
            return self::collectLegacy($policy, $type, $headers, $piiSanitizeHeaders);
        }

        $httpHeaders = $dataCollection->getHttpHeaders();
        $behavior = $type->isRequest() ? $httpHeaders['request'] : $httpHeaders['response'];
        $filtered = (new KeyValueDataFilter($behavior))->filterHeaders($headers);

        if ($filtered === null || $piiSanitizeHeaders === null) {
            return $filtered;
        }

        return self::sanitize($filtered, $piiSanitizeHeaders);
    }

    /**
     * The legacy options only collected the headers of incoming requests. Without `send_default_pii`,
     * the configured or default PII headers are sanitized.
     *
     * @param array<array-key, string[]> $headers
     * @param string[]|null              $piiSanitizeHeaders
     *
     * @return array<array-key, string[]>|null
     */
    private static function collectLegacy(DataCollectionPolicy $policy, HttpMessageType $type, array $headers, ?array $piiSanitizeHeaders): ?array
    {
        if ($type !== HttpMessageType::incomingRequest()) {
            return null;
        }

        if ($policy->shouldCollectUserInfo()) {
            return $headers;
        }

        return self::sanitize($headers, $piiSanitizeHeaders ?? self::DEFAULT_PII_SANITIZE_HEADERS);
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
