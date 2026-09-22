<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

final class RequestDataCollector
{
    /**
     * Default headers sanitized in legacy mode when send_default_pii is false.
     */
    public const DEFAULT_PII_SANITIZE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-forwarded-for',
        'x-real-ip',
    ];

    /**
     * @var DataCollectionPolicy
     */
    private $policy;

    /**
     * @var string[]|null
     */
    private $piiSanitizeHeaders;

    /**
     * @param string[]|null $piiSanitizeHeaders
     */
    public function __construct(DataCollectionPolicy $policy, ?array $piiSanitizeHeaders = null)
    {
        $this->policy = $policy;
        $this->piiSanitizeHeaders = $piiSanitizeHeaders === null ? null : array_map('strtolower', $piiSanitizeHeaders);
    }

    /**
     * @template T
     *
     * @param array<string, T> $data
     *
     * @return array<string, T>
     */
    public function collectUserInfo(array $data): array
    {
        return $this->policy->shouldCollectUserInfo() ? $data : [];
    }

    /**
     * @return array<string, string>
     */
    public function collectClientIpData(?string $ipAddress): array
    {
        if ($ipAddress === null || !$this->policy->shouldCollectUserInfo()) {
            return [];
        }

        return ['net.peer.ip' => $ipAddress];
    }

    public function shouldCollectUserInfo(): bool
    {
        return $this->policy->shouldCollectUserInfo();
    }

    public function collectQueryString(string $queryString): ?string
    {
        return HttpUrlCollector::collectQueryString($this->policy, $queryString);
    }

    /**
     * @param array<array-key, mixed> $cookies
     *
     * @return array<array-key, mixed>|null
     */
    public function collectCookies(array $cookies): ?array
    {
        $dataCollection = $this->policy->getDataCollection();
        if ($dataCollection === null) {
            if ($this->policy->shouldCollectUserInfo()) {
                return $cookies;
            }

            return null;
        }

        return HttpCookieCollector::collect($dataCollection, $cookies);
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<array-key, string[]>|null
     */
    public function collectHeaders(array $headers): ?array
    {
        $dataCollection = $this->policy->getDataCollection();
        if ($dataCollection === null) {
            if ($this->policy->shouldCollectUserInfo()) {
                return $headers;
            }

            return $this->sanitizeHeaders($headers);
        }

        $headers = KeyValueDataFilter::filterHeaders($headers, $dataCollection->getHttpHeaders()['request']);

        return $headers === null ? null : $this->sanitizeHeaders($headers);
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<array-key, string[]>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        $restrictedHeaders = $this->piiSanitizeHeaders ?? ($this->policy->isLegacyMode() ? self::DEFAULT_PII_SANITIZE_HEADERS : []);

        foreach ($headers as $name => $values) {
            $name = (string) $name;
            $shouldSanitize = \in_array(strtolower($name), $restrictedHeaders, true);
            $sanitized[$name] = [];

            foreach ($values as $headerLine => $headerValue) {
                $sanitized[$name][$headerLine] = $shouldSanitize ? KeyValueDataFilter::FILTERED_VALUE : $headerValue;
            }
        }

        return $sanitized;
    }
}
