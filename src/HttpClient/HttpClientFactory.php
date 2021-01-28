<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

use GuzzleHttp\RequestOptions as GuzzleHttpClientOptions;
use Http\Adapter\Guzzle6\Client as GuzzleHttpClient;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\DecoderPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Curl\Client as CurlHttpClient;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Message\ResponseFactory as ResponseFactoryInterface;
use Http\Message\StreamFactory as StreamFactoryInterface;
use Http\Message\UriFactory as UriFactoryInterface;
use Sentry\HttpClient\Authentication\SentryAuthentication;
use Sentry\HttpClient\Plugin\GzipEncoderPlugin;
use Sentry\Options;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Component\HttpClient\HttplugClient as SymfonyHttplugClient;

/**
 * Default implementation of the {@HttpClientFactoryInterface} interface that uses
 * Httplug to autodiscover the HTTP client if none is passed by the user.
 */
final class HttpClientFactory implements HttpClientFactoryInterface
{
    /**
     * @var int The timeout of the request in seconds
     */
    private const DEFAULT_HTTP_TIMEOUT = 5;

    /**
     * @var int The default number of seconds to wait while trying to connect
     *          to a server
     */
    private const DEFAULT_HTTP_CONNECT_TIMEOUT = 2;

    /**
     * @var UriFactoryInterface The PSR-7 URI factory
     */
    private $uriFactory;

    /**
     * @var ResponseFactoryInterface The PSR-7 response factory
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface The PSR-17 stream factory
     */
    private $streamFactory;

    /**
     * @var HttpAsyncClientInterface|null The HTTP client
     */
    private $httpClient;

    /**
     * @var string The name of the SDK
     */
    private $sdkIdentifier;

    /**
     * @var string The version of the SDK
     */
    private $sdkVersion;

    /**
     * Constructor.
     *
     * @param UriFactoryInterface           $uriFactory      The PSR-7 URI factory
     * @param ResponseFactoryInterface      $responseFactory The PSR-7 response factory
     * @param StreamFactoryInterface        $streamFactory   The PSR-17 stream factory
     * @param HttpAsyncClientInterface|null $httpClient      The HTTP client
     * @param string                        $sdkIdentifier   The SDK identifier
     * @param string                        $sdkVersion      The SDK version
     */
    public function __construct(
        UriFactoryInterface $uriFactory,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        ?HttpAsyncClientInterface $httpClient,
        string $sdkIdentifier,
        string $sdkVersion
    ) {
        $this->uriFactory = $uriFactory;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->httpClient = $httpClient;
        $this->sdkIdentifier = $sdkIdentifier;
        $this->sdkVersion = $sdkVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Options $options): HttpAsyncClientInterface
    {
        if (null === $options->getDsn(false)) {
            throw new \RuntimeException('Cannot create an HTTP client without the Sentry DSN set in the options.');
        }

        $httpClient = $this->httpClient;

        if (null !== $httpClient && null !== $options->getHttpProxy()) {
            throw new \RuntimeException('The "http_proxy" option does not work together with a custom HTTP client.');
        }

        if (null === $httpClient) {
            if (class_exists(SymfonyHttplugClient::class)) {
                $symfonyConfig = [
                    'max_duration' => self::DEFAULT_HTTP_TIMEOUT,
                ];

                if (null !== $options->getHttpProxy()) {
                    $symfonyConfig['proxy'] = $options->getHttpProxy();
                }

                /** @psalm-suppress UndefinedClass */
                $httpClient = new SymfonyHttplugClient(
                    SymfonyHttpClient::create($symfonyConfig)
                );
            } elseif (class_exists(GuzzleHttpClient::class)) {
                /** @psalm-suppress UndefinedClass */
                $guzzleConfig = [
                    GuzzleHttpClientOptions::TIMEOUT => self::DEFAULT_HTTP_TIMEOUT,
                    GuzzleHttpClientOptions::CONNECT_TIMEOUT => self::DEFAULT_HTTP_CONNECT_TIMEOUT,
                ];

                if (null !== $options->getHttpProxy()) {
                    /** @psalm-suppress UndefinedClass */
                    $guzzleConfig[GuzzleHttpClientOptions::PROXY] = $options->getHttpProxy();
                }

                /** @psalm-suppress InvalidPropertyAssignmentValue */
                $httpClient = GuzzleHttpClient::createWithConfig($guzzleConfig);
            } elseif (class_exists(CurlHttpClient::class)) {
                $curlConfig = [
                    \CURLOPT_TIMEOUT => self::DEFAULT_HTTP_TIMEOUT,
                    \CURLOPT_CONNECTTIMEOUT => self::DEFAULT_HTTP_CONNECT_TIMEOUT,
                ];

                if (null !== $options->getHttpProxy()) {
                    $curlConfig[\CURLOPT_PROXY] = $options->getHttpProxy();
                }

                /** @psalm-suppress InvalidPropertyAssignmentValue */
                $httpClient = new CurlHttpClient(null, null, $curlConfig);
            } elseif (null !== $options->getHttpProxy()) {
                throw new \RuntimeException('The "http_proxy" option requires either the "php-http/curl-client" or the "php-http/guzzle6-adapter" package to be installed.');
            }
        }

        if (null === $httpClient) {
            $httpClient = HttpAsyncClientDiscovery::find();
        }

        $httpClientPlugins = [
            new HeaderSetPlugin(['User-Agent' => $this->sdkIdentifier . '/' . $this->sdkVersion]),
            new AuthenticationPlugin(new SentryAuthentication($options, $this->sdkIdentifier, $this->sdkVersion)),
            new RetryPlugin(['retries' => $options->getSendAttempts()]),
            new ErrorPlugin(),
        ];

        if ($options->isCompressionEnabled()) {
            $httpClientPlugins[] = new GzipEncoderPlugin($this->streamFactory);
            $httpClientPlugins[] = new DecoderPlugin();
        }

        return new PluginClient($httpClient, $httpClientPlugins);
    }
}
