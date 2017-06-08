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

use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Raven\Transport\CurlTransportFactory;
use Raven\Transport\PluginClientFactory;
use Raven\Transport\TransportFactoryInterface;

/**
 * The default implementation of {@link ClientBuilderInterface}.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * @method bool isTrustXForwardedProto()
 * @method setIsTrustXForwardedProto(bool $value)
 * @method string[] getPrefixes()
 * @method setPrefixes(array $prefixes)
 * @method bool getSerializeAllObjects()
 * @method setSerializeAllObjects(bool $serializeAllObjects)
 * @method string getCurlMethod()
 * @method setCurlMethod(string $method)
 * @method string getCurlPath()
 * @method setCurlPath(string $path)
 * @method bool getCurlIpv4()
 * @method setCurlIpv4(bool $enable)
 * @method string getCurlSslVersion()
 * @method setCurlSslVersion(string $version)
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
 * @method int getOpenTimeout()
 * @method setOpenTimeout(int $timeout)
 * @method int getTimeout()
 * @method setTimeout(int $timeout)
 * @method string getProxy()
 * @method setProxy(string $proxy)
 * @method string getRelease()
 * @method setRelease(string $release)
 * @method string getServerName()
 * @method setServerName(string $serverName)
 * @method array getSslOptions()
 * @method setSslOptions(array $options)
 * @method bool isSslVerificationEnabled()
 * @method setSslVerificationEnabled(bool $enable)
 * @method string getSslCaFile()
 * @method setSslCaFile(string $path)
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
     * @var MessageFactory The message factory
     */
    protected $messageFactory;

    /**
     * @var StreamFactory The stream factory
     */
    protected $streamFactory;

    /**
     * @var TransportFactoryInterface The transport factory
     */
    protected $transportFactory;

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

    public function setMessageFactory(MessageFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    public function setStreamFactory(StreamFactory $streamFactory)
    {
        $this->streamFactory = $streamFactory;
    }

    /**
     * Sets the factory to use to create the transport.
     *
     * @param TransportFactoryInterface $transportFactory The transport factory
     */
    public function setTransportFactory(TransportFactoryInterface $transportFactory)
    {
        $this->transportFactory = $transportFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient()
    {
        $messageFactory = $this->messageFactory ?: MessageFactoryDiscovery::find();
        $transportFactory = $this->transportFactory ?: new CurlTransportFactory(
            $messageFactory,
            $this->streamFactory ?: StreamFactoryDiscovery::find()
        );

        $transportFactory = new PluginClientFactory($this->configuration, $transportFactory, UriFactoryDiscovery::find());

        return new Client($this->configuration, $transportFactory->getInstance(), $messageFactory);
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
}
