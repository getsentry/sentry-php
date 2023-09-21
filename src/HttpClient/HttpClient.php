<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

use Sentry\Client;
use Sentry\Options;

class HttpClient implements HttpClientInterface
{
    /**
     * @var Options
     */
    protected $options;

    /**
     * @var string The Sentry SDK identifier
     */
    protected $sdkIdentifier;

    /**
     * @var string The Sentry SDK identifier
     */
    protected $sdkVersion;

    public function __construct(Options $options, string $sdkIdentifier, string $sdkVersion)
    {
        $this->options = $options;
        $this->sdkIdentifier = $sdkIdentifier;
        $this->sdkVersion = $sdkVersion;
    }

    public function sendRequest(string $requestData): Response
    {
        $dsn = $this->options->getDsn();
        if (null === $dsn) {
            throw new \RuntimeException('The DSN option must be set to use the HttpClient.');
        }

        $curlHandle = curl_init();
        curl_setopt($curlHandle, \CURLOPT_URL, $dsn->getEnvelopeApiEndpointUrl());
        curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, $this->getRequestHeaders());
        curl_setopt($curlHandle, \CURLOPT_USERAGENT, $this->sdkIdentifier . '/' . $this->sdkVersion);
        curl_setopt($curlHandle, \CURLOPT_TIMEOUT, $this->options->getHttpTimeout());
        curl_setopt($curlHandle, \CURLOPT_CONNECTTIMEOUT, $this->options->getHttpConnectTimeout());
        curl_setopt($curlHandle, \CURLOPT_ENCODING, '');
        curl_setopt($curlHandle, \CURLOPT_POST, true);
        curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, \CURLOPT_HEADER, true);
        /**
         * @TODO(michi) make this configurable
         *
         * If we add support for CURL_HTTP_VERSION_2_0, we need
         * case-insensitive header handling, as HTTP 2.0 headers
         * are all lowercase.
         */
        curl_setopt($curlHandle, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);

        $httpSslVerifyPeer = $this->options->getHttpSslVerifyPeer();
        if ($httpSslVerifyPeer) {
            curl_setopt($curlHandle, \CURLOPT_SSL_VERIFYPEER, true);
        }

        $httpProxy = $this->options->getHttpProxy();
        if (null !== $httpProxy) {
            curl_setopt($curlHandle, \CURLOPT_PROXY, $httpProxy);
        }

        $httpProxyAuthentication = $this->options->getHttpProxyAuthentication();
        if (null !== $httpProxyAuthentication) {
            curl_setopt($curlHandle, \CURLOPT_PROXYUSERPWD, $httpProxyAuthentication);
        }

        /**
         * @TODO(michi) add request compression (gzip/brotli) depending on availiable extensions
         */
        $body = curl_exec($curlHandle);

        if (false === $body) {
            $errorCode = curl_errno($curlHandle);
            $error = curl_error($curlHandle);
            curl_close($curlHandle);

            $message = 'cURL Error (' . $errorCode . ') ' . $error;

            return new Response(0, [], $message);
        }

        $statusCode = curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($curlHandle, \CURLINFO_HEADER_SIZE);
        $headers = $this->getResponseHeaders($headerSize, (string) $body);

        curl_close($curlHandle);

        return new Response($statusCode, $headers, '');
    }

    /**
     * @return string[]
     */
    protected function getRequestHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/x-sentry-envelope',
        ];

        $dsn = $this->options->getDsn();
        if (null === $dsn) {
            return $headers;
        }

        $data = [
            'sentry_version' => Client::PROTOCOL_VERSION,
            'sentry_client' => $this->sdkIdentifier . '/' . $this->sdkVersion,
            'sentry_key' => $dsn->getPublicKey(),
        ];

        if (null !== $dsn->getSecretKey()) {
            $data['sentry_secret'] = $dsn->getSecretKey();
        }

        $authHeader = [];
        foreach ($data as $headerKey => $headerValue) {
            $authHeader[] = $headerKey . '=' . $headerValue;
        }

        return array_merge($headers, [
            'X-Sentry-Auth' => 'Sentry ' . implode(', ', $authHeader),
        ]);
    }

    /**
     * @TODO(michi) This might need a bit more love,
     * but we only really care about X-Sentry-Rate-Limits and Retry-After
     *
     * @return string[]
     */
    protected function getResponseHeaders(?int $headerSize, string $body): array
    {
        $headers = [];
        $rawHeaders = explode("\r\n", trim(substr($body, 0, $headerSize)));

        foreach ($rawHeaders as $value) {
            if (!str_contains($value, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $value, 2);
            $value = trim($value);
            $name = trim($name);

            $headers[$name] = $value;
        }

        return $headers;
    }
}
