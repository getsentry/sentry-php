<?php

declare(strict_types=1);

namespace Sentry\DataCollection;

/**
 * This collector exposes methods to collect data based on pii/data_collection settings.
 *
 * It supports both the legacy sendDefaultPii option and the new data_collection spec
 * so that the caller side doesn't have to deal with it.
 *
 * The collector can also be used in the framework SDKs without having to re-implement the
 * logic and test everything again. Ideally they only implement thin adapters that extract
 * plain header/cookie/query/body data from their request objects and feed it through this
 * class.
 *
 * @internal
 */
final class RequestDataCollector
{
    /**
     * List of headers in lowercase that will be sanitized when using the legacy pii flag.
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
     * @param DataCollectionOptions|null $dataCollection     The data collection configuration, or null to use the legacy `send_default_pii` behavior
     * @param bool                       $sendDefaultPii     The value of the legacy `send_default_pii` option
     * @param string[]                   $piiSanitizeHeaders The lowercase names of the headers to sanitize in legacy mode
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
            // The legacy behavior collects the query string untouched and
            // skips it when it evaluates to a falsy value
            return $queryString ?: null;
        }

        $behavior = $this->dataCollection->getQueryParams();

        if ($behavior['mode'] === 'off' || $queryString === '') {
            return null;
        }

        return SensitiveDataScrubber::scrubQueryString($queryString, $behavior);
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

        $behavior = $this->dataCollection->getCookies();

        if ($behavior['mode'] === 'off') {
            return null;
        }

        return SensitiveDataScrubber::scrubKeyValueData($cookies, $behavior);
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

        $behavior = $this->dataCollection->getHttpHeaders()['request'];

        if ($behavior['mode'] === 'off') {
            return null;
        }

        return SensitiveDataScrubber::scrubHeaders($headers, $behavior);
    }

    public function shouldCollectRequestBody(): bool
    {
        if ($this->dataCollection === null) {
            // The legacy behavior always captures the body, subject only to
            // the `max_request_body_size` option enforced by the caller
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
        if (empty($body)) {
            return null;
        }

        if ($this->dataCollection === null) {
            return $body;
        }

        if (!$this->shouldCollectRequestBody()) {
            return null;
        }

        return \is_array($body) ? $body : '[Filtered]';
    }

    /**
     * @param array<array-key, string[]> $headers
     *
     * @return array<array-key, string[]>
     */
    private function sanitizeLegacyHeaders(array $headers): array
    {
        foreach ($headers as $name => $values) {
            $name = (string) $name;

            if (!\in_array(strtolower($name), $this->piiSanitizeHeaders, true)) {
                continue;
            }

            foreach ($values as $headerLine => $headerValue) {
                $headers[$name][$headerLine] = '[Filtered]';
            }
        }

        return $headers;
    }
}
