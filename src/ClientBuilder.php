<?php

declare(strict_types=1);

namespace Sentry;

use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\DecoderPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use Sentry\HttpClient\Authentication\SentryAuth;
use Sentry\Integration\ErrorHandlerIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Sentry\Transport\TransportInterface;

/**
 * The default implementation of {@link ClientBuilderInterface}.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * @method int getSendAttempts()
 * @method setSendAttempts(int $attemptsCount)
 * @method string[] getPrefixes()
 * @method setPrefixes(array $prefixes)
 * @method float getSampleRate()
 * @method setSampleRate(float $sampleRate)
 * @method bool shouldAttachStacktrace()
 * @method setAttachStacktrace(bool $enable)
 * @method int getContextLines()
 * @method setContextLines(int $contextLines)
 * @method null|string getEnvironment()
 * @method setEnvironment(null|string $environment)
 * @method string[] getExcludedProjectPaths()
 * @method setExcludedProjectPaths(string[] $paths)
 * @method setExcludedLoggers(string[] $loggers)
 * @method string[] getExcludedExceptions()
 * @method string getProjectRoot()
 * @method setProjectRoot(string $path)
 * @method string getLogger()
 * @method setLogger(string $logger)
 * @method string getRelease()
 * @method setRelease(string $release)
 * @method string getDsn()
 * @method string getServerName()
 * @method setServerName(string $serverName)
 * @method string[] getTags()
 * @method setTags(string[] $tags)
 * @method bool shouldSendDefaultPii()
 * @method setSendDefaultPii(bool $enable)
 * @method bool getDefaultIntegrations()
 * @method setDefaultIntegrations(bool $enable)
 */
final class ClientBuilder implements ClientBuilderInterface
{
    /**
     * @var Options The client options
     */
    private $options;

    /**
     * @var UriFactory|null The PSR-7 URI factory
     */
    private $uriFactory;

    /**
     * @var MessageFactory|null The PSR-7 message factory
     */
    private $messageFactory;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

    /**
     * @var HttpAsyncClient|null The HTTP client
     */
    private $httpClient;

    /**
     * @var Plugin[] The list of Httplug plugins
     */
    private $httpClientPlugins = [];

    /**
     * @var SerializerInterface The serializer to be injected in the client
     */
    private $serializer;

    /**
     * @var RepresentationSerializerInterface The representation serializer to be injected in the client
     */
    private $representationSerializer;

    /**
     * @var IntegrationInterface[] List of default integrations
     */
    private $integrations = [];

    /**
     * Class constructor.
     *
     * @param array $options The client options
     */
    public function __construct(array $options = [])
    {
        $this->options = new Options($options);

        if ($this->options->getDefaultIntegrations()) {
            $this->integrations = \array_merge([
                new ErrorHandlerIntegration(),
                new RequestIntegration($this->options),
            ], $this->options->getIntegrations());
        } else {
            $this->integrations = $this->options->getIntegrations();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function create(array $options = []): self
    {
        return new static($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setUriFactory(UriFactory $uriFactory): self
    {
        $this->uriFactory = $uriFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMessageFactory(MessageFactory $messageFactory): self
    {
        $this->messageFactory = $messageFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpClient(HttpAsyncClient $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addHttpClientPlugin(Plugin $plugin): self
    {
        $this->httpClientPlugins[] = $plugin;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeHttpClientPlugin(string $className): self
    {
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
    public function setSerializer(SerializerInterface $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRepresentationSerializer(RepresentationSerializerInterface $representationSerializer): self
    {
        $this->representationSerializer = $representationSerializer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ClientInterface
    {
        $this->messageFactory = $this->messageFactory ?? MessageFactoryDiscovery::find();
        $this->uriFactory = $this->uriFactory ?? UriFactoryDiscovery::find();
        $this->httpClient = $this->httpClient ?? HttpAsyncClientDiscovery::find();
        $this->transport = $this->transport ?? $this->createTransportInstance();
        $this->serializer = $this->serializer ?? new Serializer();
        $this->representationSerializer = $this->representationSerializer ?? new RepresentationSerializer();

        return new Client($this->options, $this->transport, $this->serializer, $this->representationSerializer, $this->integrations);
    }

    /**
     * This method forwards all methods calls to the options object.
     *
     * @param string $name      The name of the method being called
     * @param array  $arguments Parameters passed to the $name'ed method
     *
     * @return $this
     *
     * @throws \BadMethodCallException If the called method does not exists
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->options, $name)) {
            throw new \BadMethodCallException(sprintf('The method named "%s" does not exists.', $name));
        }

        return $this->options->$name(...$arguments);
    }

    /**
     * Creates a new instance of the HTTP client.
     *
     * @return PluginClient
     */
    private function createHttpClientInstance(): PluginClient
    {
        if (null === $this->uriFactory) {
            throw new \RuntimeException('The PSR-7 URI factory must be set.');
        }

        if (null === $this->httpClient) {
            throw new \RuntimeException('The PSR-18 HTTP client must be set.');
        }

        if (null !== $this->options->getDsn()) {
            $this->addHttpClientPlugin(new BaseUriPlugin($this->uriFactory->createUri($this->options->getDsn())));
        }

        $this->addHttpClientPlugin(new HeaderSetPlugin(['User-Agent' => Client::USER_AGENT]));
        $this->addHttpClientPlugin(new AuthenticationPlugin(new SentryAuth($this->options)));
        $this->addHttpClientPlugin(new RetryPlugin(['retries' => $this->options->getSendAttempts()]));
        $this->addHttpClientPlugin(new ErrorPlugin());

        if ($this->options->isCompressionEnabled()) {
            $this->addHttpClientPlugin(new DecoderPlugin());
        }

        return new PluginClient($this->httpClient, $this->httpClientPlugins);
    }

    /**
     * Creates a new instance of the transport mechanism.
     *
     * @return TransportInterface
     */
    private function createTransportInstance(): TransportInterface
    {
        if (null !== $this->transport) {
            return $this->transport;
        }

        if (null === $this->options->getDsn()) {
            return new NullTransport();
        }

        if (null === $this->messageFactory) {
            throw new \RuntimeException('The PSR-7 message factory must be set.');
        }

        return new HttpTransport($this->options, $this->createHttpClientInstance(), $this->messageFactory);
    }
}
