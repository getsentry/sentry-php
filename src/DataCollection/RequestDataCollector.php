<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * Collects event request data while preserving the temporary legacy mode.
 *
 * This is shared infrastructure for first-party SDK integrations. It is
 * public in PHP terms so framework SDKs can reuse the same behavior.
 */
final class RequestDataCollector
{
    /**
     * Headers sanitized by the legacy request integration when
     * `send_default_pii` is disabled.
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
     * @param string[]|null $piiSanitizeHeaders Explicit lowercase header restrictions; null uses legacy defaults only in legacy mode
     */
    public function __construct(DataCollectionPolicy $policy, ?array $piiSanitizeHeaders = null)
    {
        $this->policy = $policy;
        $this->piiSanitizeHeaders = $piiSanitizeHeaders;
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
        return HttpDataCollector::collectQueryString($this->policy, $queryString);
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
            return $this->policy->shouldCollectUserInfo() ? $cookies : null;
        }

        return KeyValueDataFilter::filterCookies($cookies, $dataCollection->getCookies());
    }

    /**
     * Returns the safe fallback required when a raw Cookie header cannot be parsed.
     *
     * @param string[] $cookieHeaders
     *
     * @return array<string, string[]>
     */
    public function collectMalformedCookieHeader(array $cookieHeaders): array
    {
        $dataCollection = $this->policy->getDataCollection();
        if ($dataCollection === null || $dataCollection->getCookies()['mode'] === 'off') {
            return [];
        }

        $malformed = false;
        HttpDataCollector::parseRequestCookies($cookieHeaders, $malformed);

        return $malformed ? ['Cookie' => [KeyValueDataFilter::FILTERED_VALUE]] : [];
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
            return $this->policy->shouldCollectUserInfo() ? $headers : $this->sanitizeHeaders($headers);
        }

        $headers = KeyValueDataFilter::filterHeaders($headers, $dataCollection->getHttpHeaders()['request']);

        return $headers === null ? null : $this->sanitizeHeaders($headers);
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<string, string[]>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        $restrictedHeaders = $this->piiSanitizeHeaders ?? ($this->policy->isLegacyMode() ? self::DEFAULT_PII_SANITIZE_HEADERS : []);

        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (\in_array(strtolower($name), $restrictedHeaders, true)) {
                foreach ($values as $headerLine => $headerValue) {
                    $values[$headerLine] = KeyValueDataFilter::FILTERED_VALUE;
                }
            }

            $sanitized[$name] = $values;
        }

        return $sanitized;
    }
}
