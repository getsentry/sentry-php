<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Transport;

use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\PluginClient;
use Http\Message\UriFactory;
use Raven\Client;
use Raven\Configuration;
use Raven\Transport\Authentication\SentryAuth;

/**
 *
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class PluginClientFactory implements TransportFactoryInterface
{
    /**
     * @var Configuration The Raven client configuration
     */
    private $configuration;

    /**
     * @var TransportFactoryInterface The transport factory
     */
    private $transportFactory;

    /**
     * @var UriFactory The URI factory
     */
    private $uriFactory;

    /**
     * Constructor.
     *
     * @param Configuration             $configuration    The Raven client configuration
     * @param TransportFactoryInterface $transportFactory The transport factory
     * @param UriFactory                $uriFactory       The URI factory
     */
    public function __construct(Configuration $configuration, TransportFactoryInterface $transportFactory, UriFactory $uriFactory)
    {
        $this->configuration = $configuration;
        $this->transportFactory = $transportFactory;
        $this->uriFactory = $uriFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(array $options = [])
    {
        $client = $this->transportFactory->getInstance($options);
        $plugins = [];

        if (null !== $this->configuration->getServer()) {
            $plugins[] = $this->getBaseUriPlugin();
        }

        $plugins[] = $this->getHeadersPlugin();
        $plugins[] = $this->getAuthenticationPlugin();
        $plugins[] = $this->getErrorsPlugin();

        return new PluginClient($client, $plugins);
    }

    /**
     * Gets an instance of the plugin that sets the base URI address of the
     * Sentry server.
     *
     * @return BaseUriPlugin
     */
    private function getBaseUriPlugin()
    {
        return new BaseUriPlugin($this->uriFactory->createUri($this->configuration->getServer()));
    }

    /**
     * Gets an instance of the plugin that sets the required header needed by
     * the Sentry server to work correctly.
     *
     * @return HeaderSetPlugin
     */
    private function getHeadersPlugin()
    {
        return new HeaderSetPlugin([
            'User-Agent' => 'sentry-php/' . Client::VERSION,
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Gets an instance of the plugin that sets the header needed by the Sentry
     * server to authenticate a request.
     *
     * @return AuthenticationPlugin
     */
    private function getAuthenticationPlugin()
    {
        $authentication = new SentryAuth($this->configuration);

        return new AuthenticationPlugin($authentication);
    }

    /**
     * Gets an instance of the plugin that converts response errors into PHP
     * exceptions.
     *
     * @return ErrorPlugin
     */
    private function getErrorsPlugin()
    {
        return new ErrorPlugin();
    }
}