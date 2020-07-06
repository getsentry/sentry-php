<?php

declare(strict_types=1);

namespace Sentry;

use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Jean85\PrettyVersions;
use Psr\Log\LoggerInterface;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\Transport\DefaultTransportFactory;
use Sentry\Transport\TransportFactoryInterface;
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
     * @var TransportFactoryInterface|null The transport factory
     */
    private $transportFactory;

    /**
     * @var TransportInterface|null The transport
     */
    private $transport;

    /**
     * @var HttpAsyncClient|null The HTTP client
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
    private $sdkVersion;

    /**
     * Class constructor.
     *
     * @param Options|null $options The client options
     */
    public function __construct(Options $options = null)
    {
        $this->options = $options ?? new Options();
        $this->sdkVersion = PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(array $options = []): ClientBuilderInterface
    {
        return new static(new Options($options));
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

    /**
     * {@inheritdoc}
     */
    public function getClient(): ClientInterface
    {
        $this->transport = $this->transport ?? $this->createTransportInstance();

        return new Client($this->options, $this->transport, $this->createEventFactory(), $this->logger);
    }

    /**
     * {@inheritdoc}
     */
    public function setTransportFactory(TransportFactoryInterface $transportFactory): ClientBuilderInterface
    {
        $this->transportFactory = $transportFactory;

        return $this;
    }

    /**
     * Creates a new instance of the transport mechanism.
     */
    private function createTransportInstance(): TransportInterface
    {
        if (null !== $this->transport) {
            return $this->transport;
        }

        $transportFactory = $this->transportFactory ?? $this->createDefaultTransportFactory();

        return $transportFactory->create($this->options);
    }

    /**
     * Instantiate the {@see EventFactory} with the configured serializers.
     */
    private function createEventFactory(): EventFactoryInterface
    {
        $this->serializer = $this->serializer ?? new Serializer($this->options);
        $this->representationSerializer = $this->representationSerializer ?? new RepresentationSerializer($this->options);

        return new EventFactory($this->serializer, $this->representationSerializer, $this->options, $this->sdkIdentifier, $this->sdkVersion);
    }

    /**
     * Creates a new instance of the {@see DefaultTransportFactory} factory.
     */
    private function createDefaultTransportFactory(): DefaultTransportFactory
    {
        $messageFactory = MessageFactoryDiscovery::find();
        $httpClientFactory = new HttpClientFactory(
            UriFactoryDiscovery::find(),
            $messageFactory,
            StreamFactoryDiscovery::find(),
            $this->httpClient,
            $this->sdkIdentifier,
            $this->sdkVersion
        );

        return new DefaultTransportFactory($messageFactory, $httpClientFactory, $this->logger);
    }
}
