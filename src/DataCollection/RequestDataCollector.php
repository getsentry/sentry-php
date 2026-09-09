<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

use Sentry\Options;

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
     * @var DataCollectionOptions|null
     */
    private $dataCollection;

    /**
     * @var bool
     */
    private $sendDefaultPii;

    /**
     * @var string[]
     */
    private $piiSanitizeHeaders;

    /**
     * @param DataCollectionOptions|null $dataCollection     The data collection configuration, or null to preserve legacy behavior
     * @param bool                       $sendDefaultPii     The legacy `send_default_pii` value
     * @param string[]|null              $piiSanitizeHeaders Explicit lowercase header restrictions; null uses legacy defaults only in legacy mode
     */
    public function __construct(
        ?DataCollectionOptions $dataCollection,
        bool $sendDefaultPii,
        ?array $piiSanitizeHeaders = null
    ) {
        $this->dataCollection = $dataCollection;
        $this->sendDefaultPii = $sendDefaultPii;
        $this->piiSanitizeHeaders = $piiSanitizeHeaders ?? ($dataCollection === null ? self::DEFAULT_PII_SANITIZE_HEADERS : []);
    }

    /**
     * @param string[]|null $piiSanitizeHeaders
     */
    public static function fromOptions(?Options $options, ?array $piiSanitizeHeaders = null): self
    {
        return new self(
            DataCollectionOptions::fromOptions($options),
            $options !== null && $options->shouldSendDefaultPii(),
            $piiSanitizeHeaders
        );
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
        return $this->shouldCollectUserInfo() ? $data : [];
    }

    /**
     * @return array<string, string>
     */
    public function collectClientIpData(?string $ipAddress): array
    {
        if ($ipAddress === null) {
            return [];
        }

        $data = $this->collectUserInfo(['ip_address' => $ipAddress]);

        return isset($data['ip_address']) ? ['net.peer.ip' => $data['ip_address']] : [];
    }

    public function usesDataCollection(): bool
    {
        return $this->dataCollection !== null;
    }

    public function shouldCollectUserInfo(): bool
    {
        if ($this->dataCollection === null) {
            return $this->sendDefaultPii;
        }

        return $this->dataCollection->shouldCollectUserInfo();
    }

    public function collectQueryString(string $queryString): ?string
    {
        return HttpDataCollector::collectQueryString($this->dataCollection, $queryString);
    }

    /**
     * @param array<array-key, mixed> $cookies
     *
     * @return array<array-key, mixed>|null
     */
    public function collectCookies(array $cookies): ?array
    {
        if ($this->dataCollection === null) {
            return $this->sendDefaultPii ? $cookies : null;
        }

        return KeyValueDataFilter::filterCookies(
            $cookies,
            $this->dataCollection->getCookies()
        );
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<array-key, string[]>|null
     */
    public function collectHeaders(array $headers): ?array
    {
        if ($this->dataCollection === null) {
            return $this->sendDefaultPii ? $headers : $this->sanitizeHeaders($headers);
        }

        $headers = KeyValueDataFilter::filterHeaders(
            $headers,
            $this->dataCollection->getHttpHeaders()['request']
        );

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

        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (\in_array(strtolower($name), $this->piiSanitizeHeaders, true)) {
                foreach ($values as $headerLine => $headerValue) {
                    $values[$headerLine] = KeyValueDataFilter::FILTERED_VALUE;
                }
            }

            $sanitized[$name] = $values;
        }

        return $sanitized;
    }
}
