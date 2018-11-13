<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Hub;
use Sentry\State\Scope;

/**
 * This integration converts an exception into a format processable by Sentry.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ExceptionIntegration implements IntegrationInterface
{
    /**
     * @var Options The client option
     */
    private $options;

    /**
     * Constructor.
     *
     * @param Options $options The client options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event, array $payload) {
            $self = Hub::getCurrent()->getIntegration($this);

            if ($self instanceof self) {
                self::applyToEvent($self, $event, $payload['exception'] ?? null);
            }

            return $event;
        });
    }

    /**
     * Stores the given exception in the passed event.
     *
     * @param self            $self      The instance of this integration
     * @param Event           $event     The event that will be enriched with the exception
     * @param null|\Throwable $exception The exception that will be processed and added to the event
     */
    public static function applyToEvent(self $self, Event $event, \Throwable $exception = null): void
    {
        if (null === $exception) {
            return;
        }

        if ($exception instanceof \ErrorException) {
            $event->setLevel(Severity::fromError($exception->getSeverity()));
        }

        $exceptions = [];
        $currentException = $exception;
        $serializer = new Serializer($self->options->getMbDetectOrder());

        do {
            if ($self->options->isExcludedException($currentException)) {
                continue;
            }

            $data = [
                'type' => \get_class($currentException),
                'value' => $serializer->serialize($currentException->getMessage()),
            ];

            if ($self->options->getAutoLogStacks()) {
                $data['stacktrace'] = Stacktrace::createFromBacktrace($self->options, $currentException->getTrace(), $currentException->getFile(), $currentException->getLine());
            }

            $exceptions[] = $data;
        } while ($currentException = $currentException->getPrevious());

        $event->setException($exceptions);
    }
}
