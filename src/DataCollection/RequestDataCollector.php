<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * @internal
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
     * @param string[]                   $piiSanitizeHeaders Lowercase header names sanitized in legacy mode
     */
    public function __construct(
        ?DataCollectionOptions $dataCollection,
        bool $sendDefaultPii,
        array $piiSanitizeHeaders = self::DEFAULT_PII_SANITIZE_HEADERS
    ) {
        $this->dataCollection = $dataCollection;
        $this->sendDefaultPii = $sendDefaultPii;
        $this->piiSanitizeHeaders = $piiSanitizeHeaders;
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
        if ($this->dataCollection === null) {
            return $queryString !== '' ? $queryString : null;
        }

        if ($queryString === '') {
            return null;
        }

        return KeyValueDataFilter::filterQueryString(
            $queryString,
            $this->dataCollection->getUrlQueryParams()
        );
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

        return KeyValueDataFilter::filterKeyValueData(
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
            return $this->sendDefaultPii ? $headers : $this->sanitizeLegacyHeaders($headers);
        }

        return KeyValueDataFilter::filterHeaders(
            $headers,
            $this->dataCollection->getHttpHeaders()['request']
        );
    }

    public function shouldCollectRequestBody(): bool
    {
        if ($this->dataCollection === null) {
            // Legacy request body collection is controlled by max_request_body_size.
            return true;
        }

        return \in_array('incomingRequest', $this->dataCollection->getHttpBodies(), true);
    }

    /**
     * @param mixed $body
     *
     * @return mixed
     */
    public function collectRequestBody($body)
    {
        if (empty($body) || !$this->shouldCollectRequestBody()) {
            return null;
        }

        if ($this->dataCollection === null) {
            return $body;
        }

        if (!\is_array($body)) {
            return '[Filtered]';
        }

        return KeyValueDataFilter::filterKeyValueData($body, [
            'mode' => 'denyList',
            'terms' => [],
        ]);
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<string, string[]>
     */
    private function sanitizeLegacyHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (\in_array(strtolower($name), $this->piiSanitizeHeaders, true)) {
                foreach ($values as $headerLine => $headerValue) {
                    $values[$headerLine] = '[Filtered]';
                }
            }

            $sanitized[$name] = $values;
        }

        return $sanitized;
    }
}
