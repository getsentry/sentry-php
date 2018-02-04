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

use Http\Client\HttpAsyncClient;
use Http\Message\Encoding\CompressStream;
use Http\Message\RequestFactory;
use Http\Promise\Promise;
use Raven\Configuration;
use Raven\Event;
use Raven\HttpClient\Encoding\Base64EncodingStream;
use Raven\Util\JSON;

/**
 * This transport sends the events using an HTTP client.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class HttpTransport implements TransportInterface
{
    /**
     * @var Configuration The Raven client configuration
     */
    private $config;

    /**
     * @var HttpAsyncClient The HTTP client
     */
    private $httpClient;

    /**
     * @var RequestFactory The PSR-7 request factory
     */
    private $requestFactory;

    /**
     * @var Promise[] The list of pending requests
     */
    private $pendingRequests = [];

    /**
     * Constructor.
     *
     * @param Configuration   $config         The Raven client configuration
     * @param HttpAsyncClient $httpClient     The HTTP client
     * @param RequestFactory  $requestFactory The PSR-7 request factory
     */
    public function __construct(Configuration $config, HttpAsyncClient $httpClient, RequestFactory $requestFactory)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;

        register_shutdown_function(function () {
            // When the library will support PHP 7.1+ only this closure can be
            // replaced with a simple call to \Closure::fromCallable
            $this->cleanupPendingRequests();
        });
    }

    /**
     * Destructor. Ensures that all pending requests ends before destroying this
     * object instance.
     */
    public function __destruct()
    {
        $this->cleanupPendingRequests();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event)
    {
        $request = $this->requestFactory->createRequest(
            'POST',
            sprintf('api/%d/store/', $this->config->getProjectId()),
            ['Content-Type' => $this->isEncodingCompressed() ? 'application/octet-stream' : 'application/json'],
            JSON::encode($event)
        );

        if ($this->isEncodingCompressed()) {
            $request = $request->withBody(
                new Base64EncodingStream(
                    new CompressStream($request->getBody())
                )
            );
        }

        $promise = $this->httpClient->sendAsyncRequest($request);

        // This function is defined in-line so it doesn't show up for type-hinting
        $cleanupPromiseCallback = function ($responseOrException) use ($promise) {
            $index = array_search($promise, $this->pendingRequests, true);

            if (false !== $index) {
                unset($this->pendingRequests[$index]);
            }

            return $responseOrException;
        };

        $promise->then($cleanupPromiseCallback, $cleanupPromiseCallback);

        $this->pendingRequests[] = $promise;

        return true;
    }

    /**
     * Checks whether the encoding is compressed.
     *
     * @return bool
     */
    private function isEncodingCompressed()
    {
        return 'gzip' === $this->config->getEncoding();
    }

    /**
     * Cleanups the pending requests by forcing them to be sent. Any error that
     * occurs will be ignored.
     */
    private function cleanupPendingRequests()
    {
        foreach ($this->pendingRequests as $pendingRequest) {
            try {
                $pendingRequest->wait();
            } catch (\Exception $exception) {
            }
        }
    }
}
