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

use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Raven\HttpClient\HttpClientFactoryInterface;

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
     * Sets the factory to use to create PSR-7 messages.
     *
     * @param MessageFactory $messageFactory The factory
     */
    public function setMessageFactory(MessageFactory $messageFactory);

    /**
     * Sets the factory to use to create PSR-7 streams.
     *
     * @param StreamFactory $streamFactory The factory
     */
    public function setStreamFactory(StreamFactory $streamFactory);

    /**
     * Sets the factory to use to create the HTTP client.
     *
     * @param HttpClientFactoryInterface $httpClientFactory The factory
     */
    public function setHttpClientFactory(HttpClientFactoryInterface $httpClientFactory);

    /**
     * Gets the instance of the client built using the configured options.
     *
     * @return Client
     */
    public function getClient();
}
