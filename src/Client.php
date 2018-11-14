<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Integration\Handler;
use Sentry\Integration\IntegrationInterface;
use Sentry\State\Scope;
use Sentry\Transport\TransportInterface;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Default implementation of the {@see ClientInterface} interface.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class Client implements ClientInterface
{
    /**
     * The version of the library.
     */
    public const VERSION = '2.0.x-dev';

    /**
     * The version of the protocol to communicate with the Sentry server.
     */
    public const PROTOCOL_VERSION = '7';

    /**
     * The identifier of the SDK.
     */
    public const SDK_IDENTIFIER = 'sentry.php';

    /**
     * This constant defines the client's user-agent string.
     */
    public const USER_AGENT = self:: SDK_IDENTIFIER . '/' . self::VERSION;

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
     * @var IntegrationInterface[] The stack of integrations
     */
    private $integrations;

    /**
     * Constructor.
     *
     * @param Options                $options      The client configuration
     * @param TransportInterface     $transport    The transport
     * @param IntegrationInterface[] $integrations The integrations used by the client
     */
    public function __construct(Options $options, TransportInterface $transport, array $integrations = [])
    {
        $this->options = $options;
        $this->transport = $transport;
        $this->integrations = Handler::setupIntegrations($integrations);
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
    public function addBreadcrumb(Breadcrumb $breadcrumb, ?Scope $scope = null): void
    {
        $beforeBreadcrumbCallback = $this->options->getBeforeBreadcrumbCallback();
        $maxBreadcrumbs = $this->options->getMaxBreadcrumbs();

        if ($maxBreadcrumbs <= 0) {
            return;
        }

        $breadcrumb = $beforeBreadcrumbCallback($breadcrumb);

        if (null !== $breadcrumb && null !== $scope) {
            $scope->addBreadcrumb($breadcrumb, $maxBreadcrumbs);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?string
    {
        $payload['message'] = $message;
        $payload['level'] = $level;

        return $this->captureEvent($payload, $scope);
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
            return $this->send($event);
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
    public function getIntegration(string $integrationClassName): ?IntegrationInterface
    {
        return $this->integrations[$integrationClassName] ?? null;
    }

    /**
     * Sends the given event to the Sentry server.
     *
     * @param Event $event The event to send
     *
     * @return null|string
     */
    protected function send(Event $event): ?string
    {
        return $this->transport->send($event);
    }

    /**
     * Assembles an event and prepares it to be sent of to Sentry.
     *
     * @param array      $payload the payload that will be converted to an Event
     * @param null|Scope $scope   optional scope which enriches the Event
     *
     * @return null|Event returns ready to send Event, however depending on options it can be discarded
     */
    protected function prepareEvent(array $payload, ?Scope $scope = null): ?Event
    {
        $sampleRate = $this->getOptions()->getSampleRate();
        if ($sampleRate < 1 && mt_rand(1, 100) / 100.0 > $sampleRate) {
            return null;
        }

        $event = new Event();
        $event->setServerName($this->getOptions()->getServerName());
        $event->setRelease($this->getOptions()->getRelease());
        $event->setEnvironment($this->getOptions()->getCurrentEnvironment());
        $event->getTagsContext()->merge($this->getOptions()->getTags());

        if (isset($payload['transaction'])) {
            $event->setTransaction($payload['transaction']);
        } else {
            $request = ServerRequestFactory::fromGlobals();
            $serverParams = $request->getServerParams();

            if (isset($serverParams['PATH_INFO'])) {
                $event->setTransaction($serverParams['PATH_INFO']);
            }
        }

        if (isset($payload['logger'])) {
            $event->setLogger($payload['logger']);
        }

        $message = $payload['message'] ?? null;
        $messageParams = $payload['message_params'] ?? [];

        if (null !== $message) {
            $event->setMessage(substr($message, 0, self::MESSAGE_MAX_LENGTH_LIMIT), $messageParams);
        }

        if (isset($payload['exception'])) {
            $this->addThrowableToEvent($event, $payload['exception']);
        }

        if (null !== $scope) {
            $event = $scope->applyToEvent($event, $payload);
        }

        $beforeSendCallback = $this->options->getBeforeSendCallback();

        return $beforeSendCallback($event);
    }

    /**
     * Stores the given exception in the passed event.
     *
     * @param Event           $event     The event that will be enriched with the exception
     * @param null|\Throwable $exception The exception that will be processed and added to the event
     */
    private function addThrowableToEvent(Event $event, \Throwable $exception = null): void
    {
        if (null === $exception) {
            return;
        }

        if ($exception instanceof \ErrorException) {
            $event->setLevel(Severity::fromError($exception->getSeverity()));
        }

        $exceptions = [];
        $currentException = $exception;
        $serializer = new Serializer($this->getOptions()->getMbDetectOrder());

        do {
            if ($this->getOptions()->isExcludedException($currentException)) {
                continue;
            }

            $data = [
                'type' => \get_class($currentException),
                'value' => $serializer->serialize($currentException->getMessage()),
            ];

            if ($this->getOptions()->getAutoLogStacks()) {
                $data['stacktrace'] = Stacktrace::createFromBacktrace($this->getOptions(), $currentException->getTrace(), $currentException->getFile(), $currentException->getLine());
            }

            $exceptions[] = $data;
        } while ($currentException = $currentException->getPrevious());

        $event->setException($exceptions);
    }
}
