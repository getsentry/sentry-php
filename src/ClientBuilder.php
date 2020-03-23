<?php

declare(strict_types=1);

namespace Sentry;

use Http\Client\Common\Plugin as PluginInterface;
use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory as MessageFactoryInterface;
use Http\Message\StreamFactory as StreamFactoryInterface;
use Http\Message\UriFactory as UriFactoryInterface;
use Jean85\PrettyVersions;
use Psr\Log\LoggerInterface;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\HttpClient\PluggableHttpClientFactory;
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
     * @var UriFactoryInterface|null The PSR-7 URI factory
     */
    private $uriFactory;

    /**
     * @var StreamFactoryInterface|null The PSR-17 stream factory
     */
    private $streamFactory;

    /**
     * @var MessageFactoryInterface|null The PSR-7 message factory
     */
    private $messageFactory;

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
     * @var PluginInterface[] The list of Httplug plugins
     */
    private $httpClientPlugins = [];

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
    public function setUriFactory(UriFactoryInterface $uriFactory): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.3 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);

        $this->uriFactory = $uriFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMessageFactory(MessageFactoryInterface $messageFactory): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.3 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);

        $this->messageFactory = $messageFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTransport(TransportInterface $transport): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.3 and will be removed in 3.0. Use the setTransportFactory() method instead.', __METHOD__), E_USER_DEPRECATED);

        $this->transport = $transport;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpClient(HttpAsyncClient $httpClient): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.3 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);

        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addHttpClientPlugin(PluginInterface $plugin): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.3 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);

        $this->httpClientPlugins[] = $plugin;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeHttpClientPlugin(string $className): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.3 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);

        foreach ($this->httpClientPlugins as $index => $httpClientPlugin) {
            if (!$httpClientPlugin instanceof $className) {
                continue;
            }

            unset($this->httpClientPlugins[$index]);
        }

        return $this;
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
     * Sets the version of the SDK package that generated this Event using the Packagist name.
     *
     * @param string $packageName The package name that will be used to get the version from (i.e. "sentry/sentry")
     *
     * @return $this
     *
     * @deprecated since version 2.2, to be removed in 3.0
     */
    public function setSdkVersionByPackageName(string $packageName): ClientBuilderInterface
    {
        @trigger_error(sprintf('Method %s() is deprecated since version 2.2 and will be removed in 3.0.', __METHOD__), E_USER_DEPRECATED);

        $this->sdkVersion = PrettyVersions::getVersion($packageName)->getPrettyVersion();

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
     * Sets the transport factory.
     *
     * @param TransportFactoryInterface $transportFactory The transport factory
     *
     * @return $this
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
        $this->messageFactory = $this->messageFactory ?? MessageFactoryDiscovery::find();
        $this->uriFactory = $this->uriFactory ?? UriFactoryDiscovery::find();
        $this->streamFactory = $this->streamFactory ?? StreamFactoryDiscovery::find();

        $httpClientFactory = new HttpClientFactory(
            $this->uriFactory,
            $this->messageFactory,
            $this->streamFactory,
            $this->httpClient,
            $this->sdkIdentifier,
            $this->sdkVersion
        );

        if (!empty($this->httpClientPlugins)) {
            $httpClientFactory = new PluggableHttpClientFactory($httpClientFactory, $this->httpClientPlugins);
        }

        return new DefaultTransportFactory($this->messageFactory, $httpClientFactory, $this->logger);
    }
}
