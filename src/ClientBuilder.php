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
 * The default implementation of {@link ClientBuilderInterface}.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ClientBuilder implements ClientBuilderInterface
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
     * @var SerializerInterface|null The serializer to be injected in the client
     */
    private $serializer;

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
     * {@inheritdoc}
     */
    public static function create(array $options = []): ClientBuilderInterface
    {
        return new self(new Options($options));
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer): ClientBuilderInterface
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRepresentationSerializer(RepresentationSerializerInterface $representationSerializer): ClientBuilderInterface
    {
        $this->representationSerializer = $representationSerializer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger): ClientBuilderInterface
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSdkIdentifier(string $sdkIdentifier): ClientBuilderInterface
    {
        $this->sdkIdentifier = $sdkIdentifier;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setSdkVersion(string $sdkVersion): ClientBuilderInterface
    {
        $this->sdkVersion = $sdkVersion;

        return $this;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function setTransport(TransportInterface $transport): ClientBuilderInterface
    {
        $this->transport = $transport;

        return $this;
    }

    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function setHttpClient(HttpClientInterface $httpClient): ClientBuilderInterface
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ClientInterface
    {
        return new Client($this->options, $this->transport, $this->sdkIdentifier, $this->sdkVersion, $this->serializer, $this->representationSerializer, $this->logger);
    }
}
