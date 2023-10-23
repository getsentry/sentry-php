<?php

declare(strict_types=1);

namespace Sentry;

use Psr\Log\LoggerInterface;
use Sentry\HttpClient\HttpClient;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\SerializerInterface;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\TransportInterface;

/**
 * A configurable builder for Client objects.
 *
 * @internal
 */
final class ClientBuilder
{
    /**
     * @var Options The client options
     */
    private $options;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

    /**
     * @var HttpClientInterface The HTTP client
     */
    private $httpClient;

    /**
     * @var RepresentationSerializerInterface|null The representation serializer to be injected in the client
     */
    private $representationSerializer;

    /**
     * @var LoggerInterface|null A PSR-3 logger to log internal errors and debug messages
     */
    private $logger;

    /**
     * @var string The SDK identifier, to be used in {@see Event} and {@see SentryAuth}
     */
    private $sdkIdentifier = Client::SDK_IDENTIFIER;

    /**
     * @var string The SDK version of the Client
     */
    private $sdkVersion = Client::SDK_VERSION;

    /**
     * Class constructor.
     *
     * @param Options|null $options The client options
     */
    public function __construct(Options $options = null)
    {
        $this->options = $options ?? new Options();

        $this->httpClient = $this->options->getHttpClient() ?? new HttpClient($this->sdkIdentifier, $this->sdkVersion);
        $this->transport = $this->options->getTransport() ?? new HttpTransport(
            $this->options,
            $this->httpClient,
            new PayloadSerializer($this->options),
            $this->logger
        );
    }

    /**
     * @param array<string, mixed> $options The client options, in naked array form
     */
    public static function create(array $options = []): ClientBuilder
    {
        return new self(new Options($options));
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function setRepresentationSerializer(RepresentationSerializerInterface $representationSerializer): ClientBuilder
    {
        $this->representationSerializer = $representationSerializer;

        return $this;
    }

    public function setLogger(LoggerInterface $logger): ClientBuilder
    {
        $this->logger = $logger;

        return $this;
    }

    public function setSdkIdentifier(string $sdkIdentifier): ClientBuilder
    {
        $this->sdkIdentifier = $sdkIdentifier;

        return $this;
    }

    public function setSdkVersion(string $sdkVersion): ClientBuilder
    {
        $this->sdkVersion = $sdkVersion;

        return $this;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function setTransport(TransportInterface $transport): ClientBuilder
    {
        $this->transport = $transport;

        return $this;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function setHttpClient(HttpClientInterface $httpClient): ClientBuilder
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    public function getClient(): ClientInterface
    {
        return new Client(
            $this->options,
            $this->transport,
            $this->sdkIdentifier,
            $this->sdkVersion,
            $this->representationSerializer,
            $this->logger
        );
    }
}
