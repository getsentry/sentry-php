<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Integration\Handler;
use Sentry\Integration\IntegrationInterface;
use Sentry\State\Scope;
use Sentry\Transport\TransportInterface;

/**
 * Default implementation of the {@see ClientInterface} interface.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Client implements ClientInterface
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
     * This constant defines the maximum length of the message captured by the
     * message SDK interface.
     */
    public const MESSAGE_MAX_LENGTH_LIMIT = 1024;

    /**
     * @var Options The client options
     */
    private $options;

    /**
     * @var TransportInterface The transport
     */
    private $transport;

    /**
     * @var EventFactoryInterface The factory to create {@see Event} from raw data
     */
    private $eventFactory;

    /**
     * @var IntegrationInterface[] The stack of integrations
     */
    private $integrations;

    /**
     * Constructor.
     *
     * @param Options               $options      The client configuration
     * @param TransportInterface    $transport    The transport
     * @param EventFactoryInterface $eventFactory The factory for events
     */
    public function __construct(Options $options, TransportInterface $transport, EventFactoryInterface $eventFactory)
    {
        $this->options = $options;
        $this->transport = $transport;
        $this->eventFactory = $eventFactory;
        $this->integrations = Handler::setupIntegrations($options->getIntegrations());
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
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?string
    {
        $payload = [
            'message' => $message,
            'level' => $level,
        ];

        if ($event = $this->prepareEvent($payload, $scope, $this->options->shouldAttachStacktrace())) {
            return $this->transport->send($event);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function captureException(\Throwable $exception, ?Scope $scope = null): ?string
    {
        $payload['exception'] = $exception;

        return $this->captureEvent($payload, $scope);
    }

    /**
     * {@inheritdoc}
     */
    public function captureEvent(array $payload, ?Scope $scope = null): ?string
    {
        if ($event = $this->prepareEvent($payload, $scope)) {
            return $this->transport->send($event);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function captureLastError(?Scope $scope = null): ?string
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
     */
    public function getIntegration(string $className): ?IntegrationInterface
    {
        return $this->integrations[$className] ?? null;
    }

    /**
     * Assembles an event and prepares it to be sent of to Sentry.
     *
     * @param array      $payload        the payload that will be converted to an Event
     * @param null|Scope $scope          optional scope which enriches the Event
     * @param bool       $withStacktrace True if the event should have and attached stacktrace
     *
     * @return null|Event returns ready to send Event, however depending on options it can be discarded
     */
    private function prepareEvent(array $payload, ?Scope $scope = null, bool $withStacktrace = false): ?Event
    {
        $sampleRate = $this->getOptions()->getSampleRate();
        if ($sampleRate < 1 && mt_rand(1, 100) / 100.0 > $sampleRate) {
            return null;
        }

        if ($withStacktrace) {
            $event = $this->eventFactory->createWithStacktrace($payload);
        } else {
            $event = $this->eventFactory->create($payload);
        }

        if (null !== $scope) {
            $event = $scope->applyToEvent($event, $payload);
        }

        return \call_user_func($this->options->getBeforeSendCallback(), $event);
    }
}
