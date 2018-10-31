<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry;

use Http\Client\Common\Plugin;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use Sentry\Transport\TransportInterface;

/**
 * A configurable builder for Client objects.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface ClientBuilderInterface
{
    /**
     * Creates a new instance of this builder.
     *
     * @param array $options The client options
     *
     * @return static
     */
    public static function create(array $options = []);

    /**
     * Sets the factory to use to create URIs.
     *
     * @param UriFactory $uriFactory The factory
     *
     * @return $this
     */
    public function setUriFactory(UriFactory $uriFactory);

    /**
     * Sets the factory to use to create PSR-7 messages.
     *
     * @param MessageFactory $messageFactory The factory
     *
     * @return $this
     */
    public function setMessageFactory(MessageFactory $messageFactory);

    /**
     * Sets the transport that will be used to send events.
     *
     * @param TransportInterface $transport The transport
     *
     * @return $this
     */
    public function setTransport(TransportInterface $transport);

    /**
     * Sets the HTTP client.
     *
     * @param HttpAsyncClient $httpClient The HTTP client
     *
     * @return $this
     */
    public function setHttpClient(HttpAsyncClient $httpClient);

    /**
     * Adds a new HTTP client plugin to the end of the plugins chain.
     *
     * @param Plugin $plugin The plugin instance
     *
     * @return $this
     */
    public function addHttpClientPlugin(Plugin $plugin);

    /**
     * Removes a HTTP client plugin by its fully qualified class name (FQCN).
     *
     * @param string $className The class name
     *
     * @return $this
     */
    public function removeHttpClientPlugin($className);

    /**
     * Gets the list of middlewares that will be added to the client at the
     * given priority.
     *
     * @return array
     */
    public function getMiddlewares();

    /**
     * Gets the instance of the client built using the configured options.
     *
     * @return ClientInterface
     */
    public function getClient(): ClientInterface;
}
