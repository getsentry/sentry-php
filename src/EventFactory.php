<?php

namespace Sentry;

use Zend\Diactoros\ServerRequestFactory;

final class EventFactory
{
    /**
     * @var Options The Sentry options
     */
    private $options;

    /**
     * @var string|null
     */
    private $sdkIdentifier;

    /**
     * EventFactory constructor.
     * @param Options $options
     * @param string $sdkIdentifier
     */
    public function __construct(Options $options, string $sdkIdentifier = null)
    {
        $this->options = $options;
        $this->sdkIdentifier = $sdkIdentifier;
    }

    /**
     * @param array $payload The data to be attached to the Event
     * @return Event
     * @throws \Exception
     */
    public function create(array $payload): Event
    {
        $event = new Event($this->sdkIdentifier);
        
        $event->setServerName($this->options->getServerName());
        $event->setRelease($this->options->getRelease());
        $event->getTagsContext()->merge($this->options->getTags());
        $event->setEnvironment($this->options->getEnvironment());

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
            $event->setMessage(substr($message, 0, Client::MESSAGE_MAX_LENGTH_LIMIT), $messageParams);
        }

        if (isset($payload['exception']) && $payload['exception'] instanceof \Throwable) {
            $this->addThrowableToEvent($event, $payload['exception']);
        }

        if (isset($payload['stacktrace']) && $payload['stacktrace'] instanceof Stacktrace) {
            $event->setStacktrace($payload['stacktrace']);
        }

        return $event;
    }

    /**
     * Stores the given exception in the passed event.
     *
     * @param Event      $event     The event that will be enriched with the exception
     * @param \Throwable $exception The exception that will be processed and added to the event
     */
    private function addThrowableToEvent(Event $event, \Throwable $exception): void
    {
        if ($exception instanceof \ErrorException) {
            $event->setLevel(Severity::fromError($exception->getSeverity()));
        }

        $exceptions = [];
        $currentException = $exception;

        do {
            if ($this->getOptions()->isExcludedException($currentException)) {
                continue;
            }

            $data = [
                'type' => \get_class($currentException),
                'value' => $this->serializer->serialize($currentException->getMessage()),
            ];

            $data['stacktrace'] = Stacktrace::createFromBacktrace(
                $this->getOptions(),
                $this->serializer,
                $this->representationSerializer,
                $currentException->getTrace(),
                $currentException->getFile(),
                $currentException->getLine()
            );

            $exceptions[] = $data;
        } while ($currentException = $currentException->getPrevious());

        $event->setExceptions($exceptions);
    }
}
