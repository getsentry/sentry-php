<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Http\Message\MessageFactory as MessageFactoryInterface;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface|null A PSR-3 logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param MessageFactoryInterface    $messageFactory    The PSR-7 message factory
     * @param HttpClientFactoryInterface $httpClientFactory The HTTP client factory
     * @param LoggerInterface|null       $logger            A PSR-3 logger
     */
    public function __construct(MessageFactoryInterface $messageFactory, HttpClientFactoryInterface $httpClientFactory, ?LoggerInterface $logger = null)
    {
        $this->messageFactory = $messageFactory;
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function create(Options $options): TransportInterface
    {
        if (null === $options->getDsn(false)) {
            return new NullTransport();
        }

        return new HttpTransport(
            $options,
            $this->httpClientFactory->create($options),
            $this->messageFactory,
            true,
            false,
            $this->logger
        );
    }
}
