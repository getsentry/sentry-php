<?php

namespace Raven\HttpClient;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;

/**
 * This interface implements a contract that should be respected by all factories
 * willing to create HTTP client instances.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface HttpClientFactoryInterface
{
    /**
     * Creates an instance of a HTTP client and configures it with the given
     * options.
     *
     * @param array $options Options to pass to the client
     *
     * @return HttpClient|HttpAsyncClient
     */
    public function getInstance(array $options = []);
}
