<?php

declare(strict_types=1);

namespace Sentry\HttpClient;

use Http\Client\Common\Plugin as HttpClientPluginInterface;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Sentry\Options;

/**
 * This factory can be used to decorate an HTTP client with a list of plugins.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * @deprecated since version 2.3, to be removed in 3.0
 */
final class PluggableHttpClientFactory implements HttpClientFactoryInterface
{
    /**
     * @var HttpClientFactoryInterface The HTTP factory being decorated
     */
    private $decoratedHttpClientFactory;

    /**
     * @var HttpClientPluginInterface[] The list of plugins to add to the HTTP client
     */
    private $httpClientPlugins;

    /**
     * Constructor.
     *
     * @param HttpClientFactoryInterface  $decoratedHttpClientFactory The HTTP factory being decorated
     * @param HttpClientPluginInterface[] $httpClientPlugins          The list of plugins to add to the HTTP client
     */
    public function __construct(HttpClientFactoryInterface $decoratedHttpClientFactory, array $httpClientPlugins)
    {
        @trigger_error(sprintf('The "%s" class is deprecated since version 2.3 and will be removed in 3.0.', self::class), E_USER_DEPRECATED);

        $this->decoratedHttpClientFactory = $decoratedHttpClientFactory;
        $this->httpClientPlugins = $httpClientPlugins;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Options $options): HttpAsyncClientInterface
    {
        $httpClient = $this->decoratedHttpClientFactory->create($options);

        return new PluginClient($httpClient, $this->httpClientPlugins);
    }
}
