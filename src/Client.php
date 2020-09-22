<?php

declare(strict_types=1);

namespace Sentry;

use GuzzleHttp\Promise\PromiseInterface;
use Jean85\PrettyVersions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\Exception\EventCreationException;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\IntegrationRegistry;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\State\Scope;
use Sentry\Transport\TransportInterface;

/**
 * Default implementation of the {@see ClientInterface} interface.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class Client implements ClientInterface
{
    /**
     * The version of the protocol to communicate with the Sentry server.
     */
    public const PROTOCOL_VERSION = '7';

    /**
     * The identifier of the SDK.
     */
    public const SDK_IDENTIFIER = 'sentry.php';

    /**
     * @var Options The client options
     */
    private $options;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

    /**
     * @var LoggerInterface The PSR-3 logger
     */
    private $logger;

    /**
     * @var array<string, IntegrationInterface> The stack of integrations
     *
     * @psalm-var array<class-string<IntegrationInterface>, IntegrationInterface>
     */
    private $integrations;

    /**
     * @var SerializerInterface The serializer of the client
     */
    private $serializer;

    /**
     * @var RepresentationSerializerInterface The representation serializer of the client
     */
    private $representationSerializer;

    /**
     * @var StacktraceBuilder
     */
    private $stacktraceBuilder;

    /**
     * @var string The Sentry SDK identifier
     */
    private $sdkIdentifier;

    /**
     * @var string The SDK version of the Client
     */
    private $sdkVersion;

    /**
     * Constructor.
     *
     * @param Options                                $options                  The client configuration
     * @param TransportInterface                     $transport                The transport
     * @param string|null                            $sdkIdentifier            The Sentry SDK identifier
     * @param string|null                            $sdkVersion               The Sentry SDK version
     * @param SerializerInterface|null               $serializer               The serializer
     * @param RepresentationSerializerInterface|null $representationSerializer The serializer for function arguments
     * @param LoggerInterface|null                   $logger                   The PSR-3 logger
     */
    public function __construct(
        Options $options,
        TransportInterface $transport,
        ?string $sdkIdentifier = null,
        ?string $sdkVersion = null,
        ?SerializerInterface $serializer = null,
        ?RepresentationSerializerInterface $representationSerializer = null,
        ?LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->transport = $transport;
        $this->logger = $logger ?? new NullLogger();
        $this->integrations = IntegrationRegistry::getInstance()->setupIntegrations($options, $this->logger);
        $this->serializer = $serializer ?? new Serializer($this->options);
        $this->representationSerializer = $representationSerializer ?? new RepresentationSerializer($this->options);
        $this->stacktraceBuilder = new StacktraceBuilder($options, $this->representationSerializer);
        $this->sdkIdentifier = $sdkIdentifier ?? self::SDK_IDENTIFIER;
        $this->sdkVersion = $sdkVersion ?? PrettyVersions::getVersion(PrettyVersions::getRootPackageName())->getPrettyVersion();
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?EventId
    {
        $payload = [
            'message' => $message,
            'level' => $level,
        ];

        return $this->captureEvent($payload, $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null): ?EventId
    {
        return $this->captureEvent(['exception' => $exception], $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent($payload, ?Scope $scope = null): ?EventId
    {
        $event = $this->prepareEvent($payload, $scope);

        if (null === $event) {
            return null;
        }

        try {
            /** @var Response $response */
            $response = $this->transport->send($event)->wait();
            $event = $response->getEvent();

            if (null !== $event) {
                return $event->getId();
            }
        } catch (\Throwable $exception) {
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(?Scope $scope = null): ?EventId
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $exception = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);

        return $this->captureException($exception, $scope);
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-template T of IntegrationInterface
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        /** @psalm-var T|null */
        return $this->integrations[$className] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(?int $timeout = null): PromiseInterface
    {
        return $this->transport->close($timeout);
    }

    /**
     * Assembles an event and prepares it to be sent of to Sentry.
     *
     * @param array<string, mixed>|Event $payload The payload that will be converted to an Event
     * @param Scope|null                 $scope   Optional scope which enriches the Event
     *
     * @return Event|null The prepared event object or null if it must be discarded
     */
    private function prepareEvent($payload, ?Scope $scope = null): ?Event
    {
        if ($this->options->shouldAttachStacktrace() && !($payload instanceof Event) && !isset($payload['exception']) && !isset($payload['stacktrace'])) {
            $event = $this->buildEventWithStacktrace($payload);
        } else {
            $event = $this->buildEvent($payload);
        }

        $sampleRate = $this->options->getSampleRate();

        if (EventType::transaction() !== $event->getType() && $sampleRate < 1 && mt_rand(1, 100) / 100.0 > $sampleRate) {
            $this->logger->info('The event will be discarded because it has been sampled.', ['event' => $event]);

            return null;
        }

        if (null !== $scope) {
            $previousEvent = $event;
            $event = $scope->applyToEvent($event, $payload);

            if (null === $event) {
                $this->logger->info('The event will be discarded because one of the event processors returned "null".', ['event' => $previousEvent]);

                return null;
            }
        }

        $previousEvent = $event;
        $event = ($this->options->getBeforeSendCallback())($event);

        if (null === $event) {
            $this->logger->info('The event will be discarded because the "before_send" callback returned "null".', ['event' => $previousEvent]);
        }

        return $event;
    }

    /**
     * Create an {@see Event} with a stacktrace attached to it.
     *
     * @param array<string, mixed> $payload The data to be attached to the Event
     */
    private function buildEventWithStacktrace($payload): Event
    {
        if (!isset($payload['stacktrace']) || !$payload['stacktrace'] instanceof Stacktrace) {
            $payload['stacktrace'] = $this->stacktraceBuilder->buildFromBacktrace(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                __FILE__,
                __LINE__ - 3
            );
        }

        return $this->buildEvent($payload);
    }

    /**
     * Create an {@see Event} from a data payload.
     *
     * @param array<string, mixed>|Event $payload The data to be attached to the Event
     */
    private function buildEvent($payload): Event
    {
        try {
            if ($payload instanceof Event) {
                $event = $payload;
            } else {
                $event = Event::createEvent();

                if (isset($payload['logger'])) {
                    $event->setLogger($payload['logger']);
                }

                $message = isset($payload['message']) ? mb_substr($payload['message'], 0, $this->options->getMaxValueLength()) : null;
                $messageParams = $payload['message_params'] ?? [];
                $messageFormatted = isset($payload['message_formatted']) ? mb_substr($payload['message_formatted'], 0, $this->options->getMaxValueLength()) : null;

                if (null !== $message) {
                    $event->setMessage($message, $messageParams, $messageFormatted);
                }

                if (isset($payload['exception']) && $payload['exception'] instanceof \Throwable) {
                    $this->addThrowableToEvent($event, $payload['exception']);
                }

                if (isset($payload['level']) && $payload['level'] instanceof Severity) {
                    $event->setLevel($payload['level']);
                }

                if (isset($payload['stacktrace']) && $payload['stacktrace'] instanceof Stacktrace) {
                    $event->setStacktrace($payload['stacktrace']);
                }
            }
        } catch (\Throwable $exception) {
            throw new EventCreationException($exception);
        }

        $event->setSdkIdentifier($this->sdkIdentifier);
        $event->setSdkVersion($this->sdkVersion);
        $event->setServerName($this->options->getServerName());
        $event->setRelease($this->options->getRelease());
        $event->setTags($this->options->getTags());
        $event->setEnvironment($this->options->getEnvironment());

        return $event;
    }

    /**
     * Stores the given exception in the passed event.
     *
     * @param Event      $event     The event that will be enriched with the
     *                              exception
     * @param \Throwable $exception The exception that will be processed and
     *                              added to the event
     */
    private function addThrowableToEvent(Event $event, \Throwable $exception): void
    {
        if ($exception instanceof \ErrorException) {
            $event->setLevel(Severity::fromError($exception->getSeverity()));
        }

        $exceptions = [];

        do {
            $exceptions[] = new ExceptionDataBag(
                $exception,
                $this->stacktraceBuilder->buildFromException($exception),
                new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true)
            );
        } while ($exception = $exception->getPrevious());

        $event->setExceptions($exceptions);
    }
}
