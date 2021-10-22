<?php

declare(strict_types=1);

namespace Sentry;

use GuzzleHttp\Promise\PromiseInterface;
use Jean85\PrettyVersions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        $this->sdkVersion = $sdkVersion ?? PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();
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
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        $event = Event::createEvent();
        $event->setMessage($message);
        $event->setLevel($level);

        return $this->captureEvent($event, $hint, $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        $hint = $hint ?? new EventHint();

        if (null === $hint->exception) {
            $hint->exception = $exception;
        }

        return $this->captureEvent(Event::createEvent(), $hint, $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?EventId
    {
        $event = $this->prepareEvent($event, $hint, $scope);

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
    public function captureLastError(?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $exception = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);

        return $this->captureException($exception, $scope, $hint);
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
     * @param Event          $event The payload that will be converted to an Event
     * @param EventHint|null $hint  May contain additional information about the event
     * @param Scope|null     $scope Optional scope which enriches the Event
     *
     * @return Event|null The prepared event object or null if it must be discarded
     */
    private function prepareEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?Event
    {
        if (null !== $hint) {
            if (null !== $hint->exception && empty($event->getExceptions())) {
                $this->addThrowableToEvent($event, $hint->exception);
            }

            if (null !== $hint->stacktrace && null === $event->getStacktrace()) {
                $event->setStacktrace($hint->stacktrace);
            }
        }

        $this->addMissingStacktraceToEvent($event);

        $event->setSdkIdentifier($this->sdkIdentifier);
        $event->setSdkVersion($this->sdkVersion);
        $event->setTags(array_merge($this->options->getTags(false), $event->getTags()));

        if (null === $event->getServerName()) {
            $event->setServerName($this->options->getServerName());
        }

        if (null === $event->getRelease()) {
            $event->setRelease($this->options->getRelease());
        }

        if (null === $event->getEnvironment()) {
            $event->setEnvironment($this->options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT);
        }

        if (null === $event->getLogger()) {
            $event->setLogger($this->options->getLogger(false));
        }

        $isTransaction = EventType::transaction() === $event->getType();
        $sampleRate = $this->options->getSampleRate();

        if (!$isTransaction && $sampleRate < 1 && mt_rand(1, 100) / 100.0 > $sampleRate) {
            $this->logger->info('The event will be discarded because it has been sampled.', ['event' => $event]);

            return null;
        }

        if (null !== $scope) {
            $previousEvent = $event;
            $event = $scope->applyToEvent($event, $hint);

            if (null === $event) {
                $this->logger->info('The event will be discarded because one of the event processors returned "null".', ['event' => $previousEvent]);

                return null;
            }
        }

        if (!$isTransaction) {
            $previousEvent = $event;
            $event = ($this->options->getBeforeSendCallback())($event, $hint);

            if (null === $event) {
                $this->logger->info('The event will be discarded because the "before_send" callback returned "null".', ['event' => $previousEvent]);
            }
        }

        return $event;
    }

    /**
     * Optionally adds a missing stacktrace to the Event if the client is configured to do so.
     *
     * @param Event $event The Event to add the missing stacktrace to
     */
    private function addMissingStacktraceToEvent(Event $event): void
    {
        if (!$this->options->shouldAttachStacktrace()) {
            return;
        }

        // We should not add a stacktrace when the event already has one or contains exceptions
        if (null !== $event->getStacktrace() || !empty($event->getExceptions())) {
            return;
        }

        $event->setStacktrace($this->stacktraceBuilder->buildFromBacktrace(
            debug_backtrace(0),
            __FILE__,
            __LINE__ - 3
        ));
    }

    /**
     * Stores the given exception in the passed event.
     *
     * @param Event      $event     The event that will be enriched with the exception
     * @param \Throwable $exception The exception that will be processed and added to the event
     */
    private function addThrowableToEvent(Event $event, \Throwable $exception): void
    {
        if ($exception instanceof \ErrorException && null === $event->getLevel()) {
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
