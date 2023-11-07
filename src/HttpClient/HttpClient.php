<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

use Sentry\Options;
use Sentry\Util\Http;

/**
 * @internal
 */
class HttpClient implements HttpClientInterface
{
    /**
     * @var string The Sentry SDK identifier
     */
    protected $sdkIdentifier;

    /**
     * @var string The Sentry SDK identifier
     */
    protected $sdkVersion;

    public function __construct(string $sdkIdentifier, string $sdkVersion)
    {
        $this->sdkIdentifier = $sdkIdentifier;
        $this->sdkVersion = $sdkVersion;
    }

    public function sendRequest(Request $request, Options $options): Response
    {
        $dsn = $options->getDsn();
        if ($dsn === null) {
            throw new \RuntimeException('The DSN option must be set to use the HttpClient.');
        }

        $requestData = $request->getStringBody();
        if ($requestData === null) {
            throw new \RuntimeException('The request data is empty.');
        }

        if (!\extension_loaded('curl')) {
            throw new \RuntimeException('The cURL PHP extension must be enabled to use the HttpClient.');
        }

        $curlHandle = curl_init();

        $requestHeaders = Http::getRequestHeaders($dsn, $this->sdkIdentifier, $this->sdkVersion);

        if (
            \extension_loaded('zlib')
            && $options->isHttpCompressionEnabled()
        ) {
            $requestData = gzcompress($requestData, -1, \ZLIB_ENCODING_GZIP);
            $requestHeaders[] = 'Content-Encoding: gzip';
        }

        $responseHeaders = [];
        $responseHeaderCallback = function ($curlHandle, $headerLine) use (&$responseHeaders): int {
            return Http::parseResponseHeaders($headerLine, $responseHeaders);
        };

        curl_setopt($curlHandle, \CURLOPT_URL, $dsn->getEnvelopeApiEndpointUrl());
        curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, $requestHeaders);
        curl_setopt($curlHandle, \CURLOPT_USERAGENT, $this->sdkIdentifier . '/' . $this->sdkVersion);
        curl_setopt($curlHandle, \CURLOPT_TIMEOUT, $options->getHttpTimeout());
        curl_setopt($curlHandle, \CURLOPT_CONNECTTIMEOUT, $options->getHttpConnectTimeout());
        curl_setopt($curlHandle, \CURLOPT_ENCODING, '');
        curl_setopt($curlHandle, \CURLOPT_POST, true);
        curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, \CURLOPT_HEADERFUNCTION, $responseHeaderCallback);
        curl_setopt($curlHandle, \CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);

        $httpSslVerifyPeer = $options->getHttpSslVerifyPeer();
        if ($httpSslVerifyPeer) {
            curl_setopt($curlHandle, \CURLOPT_SSL_VERIFYPEER, true);
        }

        $httpProxy = $options->getHttpProxy();
        if ($httpProxy !== null) {
            curl_setopt($curlHandle, \CURLOPT_PROXY, $httpProxy);
            curl_setopt($curlHandle, \CURLOPT_HEADEROPT, \CURLHEADER_SEPARATE);
        }

        $httpProxyAuthentication = $options->getHttpProxyAuthentication();
        if ($httpProxyAuthentication !== null) {
            curl_setopt($curlHandle, \CURLOPT_PROXYUSERPWD, $httpProxyAuthentication);
        }

        $body = curl_exec($curlHandle);

        if ($body === false) {
            $errorCode = curl_errno($curlHandle);
            $error = curl_error($curlHandle);
            curl_close($curlHandle);

            $message = 'cURL Error (' . $errorCode . ') ' . $error;

            return new Response(0, [], $message);
        }

        $statusCode = curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);

        curl_close($curlHandle);

        return new Response($statusCode, $responseHeaders, '');
    }
}
