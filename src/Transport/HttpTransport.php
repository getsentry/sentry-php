<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Event;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\Options;
use Sentry\Serializer\PayloadSerializerInterface;

/**
 * @internal
 */
class HttpTransport implements TransportInterface
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var HttpClientInterface The HTTP client
     */
    private $httpClient;

    /**
     * @var PayloadSerializerInterface The event serializer
     */
    private $payloadSerializer;

    /**
     * @var LoggerInterface A PSR-3 logger
     */
    private $logger;

    /**
     * @var RateLimiter The rate limiter
     */
    private $rateLimiter;

    /**
     * @param Options                    $options           The options
     * @param HttpClientInterface        $httpClient        The HTTP client
     * @param PayloadSerializerInterface $payloadSerializer The event serializer
     * @param LoggerInterface|null       $logger            An instance of a PSR-3 logger
     */
    public function __construct(
        Options $options,
        HttpClientInterface $httpClient,
        PayloadSerializerInterface $payloadSerializer,
        ?LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->httpClient = $httpClient;
        $this->payloadSerializer = $payloadSerializer;
        $this->logger = $logger ?? new NullLogger();
        $this->rateLimiter = new RateLimiter($this->logger);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event): Result
    {
        if ($this->options->getDsn() === null) {
            return new Result(ResultStatus::skipped(), $event);
        }

        $eventType = $event->getType();
        if ($this->rateLimiter->isRateLimited($eventType)) {
            $this->logger->warning(
                sprintf('Rate limit exceeded for sending requests of type "%s".', (string) $eventType),
                ['event' => $event]
            );

            return new Result(ResultStatus::rateLimit());
        }

        $request = new Request();
        $request->setStringBody($this->payloadSerializer->serialize($event));

        try {
            $response = $this->httpClient->sendRequest($request, $this->options);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send the event to Sentry. Reason: "%s".', $exception->getMessage()),
                ['exception' => $exception, 'event' => $event]
            );

            return new Result(ResultStatus::failed());
        }

        $response = $this->rateLimiter->handleResponse($event, $response);
        if ($response->isSuccess()) {
            return new Result(ResultStatus::success(), $event);
        }

        if ($response->hasError()) {
            $this->logger->error(
                sprintf('Failed to send the event to Sentry. Reason: "%s".', $response->getError()),
                ['event' => $event]
            );
        }

        return new Result(ResultStatus::createFromHttpStatusCode($response->getStatusCode()));
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
