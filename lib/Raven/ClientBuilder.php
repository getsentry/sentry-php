<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven;

use Http\Client\Common\Plugin;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;
use Raven\HttpClient\Authentication\SentryAuth;
use Raven\HttpClient\CurlHttpClientFactory;
use Raven\HttpClient\HttpClientFactoryInterface;

/**
 * The default implementation of {@link ClientBuilderInterface}.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * @method int getSendAttempts()
 * @method setSendAttempts(int $attemptsCount)
 * @method bool isTrustXForwardedProto()
 * @method setIsTrustXForwardedProto(bool $value)
 * @method string[] getPrefixes()
 * @method setPrefixes(array $prefixes)
 * @method bool getSerializeAllObjects()
 * @method setSerializeAllObjects(bool $serializeAllObjects)
 * @method float getSampleRate()
 * @method setSampleRate(float $sampleRate)
 * @method bool shouldInstallDefaultBreadcrumbHandlers()
 * @method setInstallDefaultBreadcrumbHandlers($installDefaultBreadcrumbHandlers)
 * @method bool shouldInstallShutdownHandler()
 * @method setInstallShutdownHandler(bool $installShutdownHandler)
 * @method string getMbDetectOrder()
 * @method setMbDetectOrder(string $detectOrder)
 * @method bool getAutoLogStacks()
 * @method setAutoLogStacks(bool $enable)
 * @method int getContextLines()
 * @method setContextLines(int $contextLines)
 * @method string getCurrentEnvironment()
 * @method setCurrentEnvironment(string $environment)
 * @method string[] getEnvironments()
 * @method setEnvironments(string[] $environments)
 * @method string[] getExcludedLoggers()
 * @method setExcludedLoggers(string[] $loggers)
 * @method string[] getExcludedExceptions()
 * @method setExcludedExceptions(string[] $exceptions)
 * @method string[] getExcludedProjectPaths()
 * @method setExcludedProjectPaths(string[] $paths)
 * @method string getProjectRoot()
 * @method setProjectRoot(string $path)
 * @method string getLogger()
 * @method setLogger(string $logger)
 * @method string getProxy()
 * @method setProxy(string $proxy)
 * @method string getRelease()
 * @method setRelease(string $release)
 * @method string getServer()
 * @method string getServerName()
 * @method setServerName(string $serverName)
 * @method string[] getTags()
 * @method setTags(string[] $tags)
 * @method string[] getProcessors()
 * @method setProcessors(string[] $processors)
 * @method array getProcessorsOptions()
 * @method setProcessorsOptions(array $options)
 */
class ClientBuilder implements ClientBuilderInterface
{
    /**
     * @var Configuration The client configuration
     */
    protected $configuration;

    /**
     * @var UriFactory The PSR-7 URI factory
     */
    protected $uriFactory;

    /**
     * @var MessageFactory The PSR-7 message factory
     */
    protected $messageFactory;

    /**
     * @var StreamFactory The PSR-7 stream factory
     */
    protected $streamFactory;

    /**
     * @var HttpClientFactoryInterface The HTTP client factory
     */
    protected $httpClientFactory;

    /**
     * @var Plugin[] The list of Httplug plugins
     */
    protected $httpClientPlugins = [];

    /**
     * Class constructor.
     *
     * @param array $options The client options
     */
    public function __construct(array $options = [])
    {
        $this->configuration = new Configuration($options);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(array $options = [])
    {
        return new static($options);
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
    public function setStreamFactory(StreamFactory $streamFactory)
    {
        $this->streamFactory = $streamFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpClientFactory(HttpClientFactoryInterface $httpClientFactory)
    {
        $this->httpClientFactory = $httpClientFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addHttpClientPlugin(Plugin $plugin)
    {
        $this->httpClientPlugins[] = $plugin;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeHttpClientPlugin($className)
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
    public function getClient()
    {
        $this->messageFactory = $this->messageFactory?: MessageFactoryDiscovery::find();
        $this->uriFactory = $this->uriFactory ?: UriFactoryDiscovery::find();
        $this->messageFactory = $this->messageFactory ?: MessageFactoryDiscovery::find();
        $this->streamFactory = $this->streamFactory ?: StreamFactoryDiscovery::find();
        $this->httpClientFactory = $this->httpClientFactory ?: new CurlHttpClientFactory($this->messageFactory, $this->streamFactory);

        return new Client($this->configuration, $this->createHttpClientInstance(), $this->messageFactory);
    }

    /**
     * This method forwards all methods calls to the configuration object.
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
        if (!method_exists($this->configuration, $name)) {
            throw new \BadMethodCallException(sprintf('The method named "%s" does not exists.', $name));
        }

        call_user_func_array([$this->configuration, $name], $arguments);

        return $this;
    }

    /**
     * Creates a new instance of the HTTP client.
     *
     * @return HttpAsyncClient
     */
    protected function createHttpClientInstance()
    {
        $httpClient = $this->httpClientFactory->getInstance($this->configuration->getHttpClientOptions());

        if (null !== $this->configuration->getServer()) {
            $this->addHttpClientPlugin(new BaseUriPlugin($this->uriFactory->createUri($this->configuration->getServer())));
        }

        $this->addHttpClientPlugin(new HeaderSetPlugin(['User-Agent' => Client::USER_AGENT]));
        $this->addHttpClientPlugin(new AuthenticationPlugin(new SentryAuth($this->configuration)));
        $this->addHttpClientPlugin(new RetryPlugin(['retries' => $this->configuration->getSendAttempts()]));
        $this->addHttpClientPlugin(new ErrorPlugin());

        return new PluginClient($httpClient, $this->httpClientPlugins);
    }
}
