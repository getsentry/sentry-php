<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\HttpClient;

use Http\Client\Curl\Client;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;

/**
 * This factory creates instances of the cURL HTTP client.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class CurlHttpClientFactory implements HttpClientFactoryInterface
{
    /**
     * @var MessageFactory The message factory
     */
    private $messageFactory;

    /**
     * @var StreamFactory The stream factory
     */
    private $streamFactory;

    /**
     * Constructor.
     *
     * @param MessageFactory $messageFactory The message factory
     * @param StreamFactory  $streamFactory  The stream factory
     */
    public function __construct(MessageFactory $messageFactory, StreamFactory $streamFactory)
    {
        $this->messageFactory = $messageFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getInstance(array $options = [])
    {
        return new Client($this->messageFactory, $this->streamFactory, $options);
    }
}
