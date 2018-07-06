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
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use Raven\HttpClient\Authentication\SentryAuth;
use Raven\Processor\ProcessorInterface;
use Raven\Processor\RemoveHttpBodyProcessor;
use Raven\Processor\SanitizeCookiesProcessor;
use Raven\Processor\SanitizeDataProcessor;
use Raven\Processor\SanitizeHttpHeadersProcessor;
use Raven\Transport\HttpTransport;
use Raven\Transport\NullTransport;
use Raven\Transport\TransportInterface;

/**
 * The default implementation of {@link ClientBuilderInterface}.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * @method int getSendAttempts()
 * @method setSendAttempts(int $attemptsCount)
 * @method string[] getPrefixes()
 * @method setPrefixes(array $prefixes)
 * @method bool getSerializeAllObjects()
 * @method setSerializeAllObjects(bool $serializeAllObjects)
 * @method float getSampleRate()
 * @method setSampleRate(float $sampleRate)
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
 * @method string getRelease()
 * @method setRelease(string $release)
 * @method string getDsn()
 * @method string getServerName()
 * @method setServerName(string $serverName)
 * @method string[] getTags()
 * @method setTags(string[] $tags)
 */
final class ClientBuilder implements ClientBuilderInterface
{
    /**
     * @var Configuration The client configuration
     */
    private $configuration;

    /**
     * @var UriFactory The PSR-7 URI factory
     */
    private $uriFactory;

    /**
     * @var MessageFactory The PSR-7 message factory
     */
    private $messageFactory;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

    /**
     * @var HttpAsyncClient The HTTP client
     */
    private $httpClient;

    /**
     * @var Plugin[] The list of Httplug plugins
     */
    private $httpClientPlugins = [];

    /**
     * @var array List of middlewares and their priorities
     */
    private $middlewares = [];

    /**
     * @var array List of processors and their priorities
     */
    private $processors = [];

    /**
     * Class constructor.
     *
     * @param array $options The client options
     */
    public function __construct(array $options = [])
    {
        $this->configuration = new Configuration($options);
        $this->processors = self::getDefaultProcessors();
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
    public function setTransport(TransportInterface $transport)
    {
        $this->transport = $transport;

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
    public function addMiddleware(callable $middleware, $priority = 0)
    {
        $this->middlewares[] = [$middleware, $priority];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeMiddleware(callable $middleware)
    {
        foreach ($this->middlewares as $key => $value) {
            if ($value[0] !== $middleware) {
                continue;
            }

            unset($this->middlewares[$key]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMiddlewares()
    {
        return $this->middlewares;
    }

    /**
     * {@inheritdoc}
     */
    public function addProcessor(ProcessorInterface $processor, $priority = 0)
    {
        $this->processors[] = [$processor, $priority];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeProcessor(ProcessorInterface $processor)
    {
        foreach ($this->processors as $key => $value) {
            if ($value[0] !== $processor) {
                continue;
            }

            unset($this->processors[$key]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessors()
    {
        return $this->processors;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient()
    {
        $this->messageFactory = $this->messageFactory ?: MessageFactoryDiscovery::find();
        $this->uriFactory = $this->uriFactory ?: UriFactoryDiscovery::find();
        $this->httpClient = $this->httpClient ?: HttpAsyncClientDiscovery::find();
        $this->transport = $this->createTransportInstance();

        $client = new Client($this->configuration, $this->transport);

        foreach ($this->processors as $value) {
            $client->addProcessor($value[0], $value[1]);
        }

        return $client;
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
    private function createHttpClientInstance()
    {
        if (null !== $this->configuration->getDsn()) {
            $this->addHttpClientPlugin(new BaseUriPlugin($this->uriFactory->createUri($this->configuration->getDsn())));
        }

        $this->addHttpClientPlugin(new HeaderSetPlugin(['User-Agent' => Client::USER_AGENT]));
        $this->addHttpClientPlugin(new AuthenticationPlugin(new SentryAuth($this->configuration)));
        $this->addHttpClientPlugin(new RetryPlugin(['retries' => $this->configuration->getSendAttempts()]));
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

        if (null !== $this->configuration->getDsn()) {
            return new HttpTransport($this->configuration, $this->createHttpClientInstance(), $this->messageFactory);
        }

        return new NullTransport();
    }

    /**
     * Returns a list of processors that are enabled by default.
     *
     * @return array
     */
    private static function getDefaultProcessors()
    {
        return [
            [new SanitizeCookiesProcessor(), 0],
            [new RemoveHttpBodyProcessor(), 0],
            [new SanitizeHttpHeadersProcessor(), 0],
            [new SanitizeDataProcessor(), -255],
        ];
    }
}
