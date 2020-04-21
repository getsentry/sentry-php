<?php

declare(strict_types=1);

namespace Sentry\Transport;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\Serializer\EventSerializerInterface;

/**
 * This transport sends the events using a syncronous HTTP client that will
 * delay sending of the requests until the shutdown of the application.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class HttpTransport implements TransportInterface
{
    /**
     * @var Options The Sentry client options
     */
    private $options;

    /**
     * @var HttpAsyncClientInterface The HTTP client
     */
    private $httpClient;

    /**
     * @var StreamFactoryInterface The PSR-7 stream factory
     */
    private $streamFactory;

    /**
     * @var RequestFactoryInterface The PSR-7 request factory
     */
    private $requestFactory;

    /**
     * @var EventSerializerInterface The event serializer
     */
    private $eventSerializer;

    /**
     * @var LoggerInterface A PSR-3 logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Options                  $options         The Sentry client configuration
     * @param HttpAsyncClientInterface $httpClient      The HTTP client
     * @param StreamFactoryInterface   $streamFactory   The PSR-7 stream factory
     * @param RequestFactoryInterface  $requestFactory  The PSR-7 request factory
     * @param EventSerializerInterface $eventSerializer The event serializer
     * @param LoggerInterface|null     $logger          An instance of a PSR-3 logger
     */
    public function __construct(
        Options $options,
        HttpAsyncClientInterface $httpClient,
        StreamFactoryInterface $streamFactory,
        RequestFactoryInterface $requestFactory,
        EventSerializerInterface $eventSerializer,
        ?LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->httpClient = $httpClient;
        $this->streamFactory = $streamFactory;
        $this->requestFactory = $requestFactory;
        $this->eventSerializer = $eventSerializer;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event): PromiseInterface
    {
        $dsn = $this->options->getDsn();

        if (null === $dsn) {
            throw new \RuntimeException(sprintf('The DSN option must be set to use the "%s" transport.', self::class));
        }

        if (EventType::transaction() === $event->getType()) {
            $request = $this->requestFactory->createRequest('POST', $dsn->getEnvelopeApiEndpointUrl())
                ->withHeader('Content-Type', 'application/x-sentry-envelope')
                ->withBody($this->streamFactory->createStream($this->eventSerializer->serialize($event)));
        } else {
            $request = $this->requestFactory->createRequest('POST', $dsn->getStoreApiEndpointUrl())
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->eventSerializer->serialize($event)));
        }

        try {
            /** @var ResponseInterface $response */
            $response = $this->httpClient->sendAsyncRequest($request)->wait();
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send the event to Sentry. Reason: "%s".', $exception->getMessage()),
                ['exception' => $exception, 'event' => $event]
            );

            return new RejectedPromise(new Response(ResponseStatus::failed(), $event));
        }

        $sendResponse = new Response(ResponseStatus::createFromHttpStatusCode($response->getStatusCode()), $event);

        if (ResponseStatus::success() === $sendResponse->getStatus()) {
            return new FulfilledPromise($sendResponse);
        }

        return new RejectedPromise($sendResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
