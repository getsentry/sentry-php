<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Http\Message\MessageFactory as MessageFactoryInterface;
use Sentry\HttpClient\HttpClientFactoryInterface;
use Sentry\Options;

/**
 * This class is the default implementation of the {@see TransportFactoryInterface}
 * interface.
 */
final class DefaultTransportFactory implements TransportFactoryInterface
{
    /**
     * @var MessageFactoryInterface The PSR-7 message factory
     */
    private $messageFactory;

    /**
     * @var HttpClientFactoryInterface The factory to create the HTTP client
     */
    private $httpClientFactory;

    /**
     * Constructor.
     *
     * @param MessageFactoryInterface    $messageFactory    The PSR-7 message factory
     * @param HttpClientFactoryInterface $httpClientFactory The HTTP client factory
     */
    public function __construct(MessageFactoryInterface $messageFactory, HttpClientFactoryInterface $httpClientFactory)
    {
        $this->messageFactory = $messageFactory;
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Options $options): TransportInterface
    {
        if (null === $options->getDsn()) {
            return new NullTransport();
        }

        return new HttpTransport(
            $options,
            $this->httpClientFactory->create($options),
            $this->messageFactory,
            true,
            false
        );
    }
}
