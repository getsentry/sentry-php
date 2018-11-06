<?php

namespace Sentry\Transport;

use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\BaseUriPlugin;
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
use Sentry\Client;
use Sentry\HttpClient\Authentication\SentryAuth;
use Sentry\Options;

final class Factory
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
     * @var TransportInterface|null The transport
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

    public static function make(Options $options, ?MessageFactory $messageFactory = null, ?HttpAsyncClient $httpClient = null, ?UriFactory $uriFactory = null): TransportInterface
    {
        $instance = new static($options, $messageFactory, $httpClient, $uriFactory);

        return $instance->createTransportInstance();
    }

    private function __construct(Options $options, ?MessageFactory $messageFactory = null, ?HttpAsyncClient $httpClient = null, ?UriFactory $uriFactory = null)
    {
        $this->options = $options;
        $this->transport = $options->getTransport();
        $this->messageFactory = $messageFactory ?? MessageFactoryDiscovery::find();
        $this->uriFactory = $uriFactory ?? UriFactoryDiscovery::find();
        $this->httpClient = $httpClient ?? HttpAsyncClientDiscovery::find();
    }

    /**
     * {@inheritdoc}
     */
    public function setUriFactory(UriFactory $uriFactory)
    {
        $this->uriFactory = $uriFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMessageFactory(MessageFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpClient(HttpAsyncClient $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    private function addHttpClientPlugin(Plugin $plugin)
    {
        $this->httpClientPlugins[] = $plugin;

        return $this;
    }

    /**
     * Creates a new instance of the HTTP client.
     *
     * @return HttpAsyncClient
     */
    private function createHttpClientInstance()
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

        // TODO maybe not use client here
        $this->addHttpClientPlugin(new HeaderSetPlugin(['User-Agent' => Client::USER_AGENT]));
        $this->addHttpClientPlugin(new AuthenticationPlugin(new SentryAuth($this->options)));
        $this->addHttpClientPlugin(new RetryPlugin(['retries' => $this->options->getSendAttempts()]));
        $this->addHttpClientPlugin(new ErrorPlugin());

        return new PluginClient($this->httpClient, $this->httpClientPlugins);
    }

    /**
     * Creates a new instance of the transport mechanism.
     *
     * @return TransportInterface
     */
    private function createTransportInstance()
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
