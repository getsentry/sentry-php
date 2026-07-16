<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionMechanism;
use Sentry\State\EventCapturer;
use Sentry\State\IsolationScope;

use function Sentry\withIsolationScope;

abstract class AbstractErrorListenerIntegration implements IntegrationInterface
{
    /**
     * @param \Throwable $exception The exception instance
     */
    protected function captureException(\Throwable $exception): void
    {
        withIsolationScope(function (IsolationScope $scope) use ($exception): void {
            $scope->addEventProcessor(function (Event $event, EventHint $hint): Event {
                return $this->addExceptionMechanismToEvent($event);
            });

            EventCapturer::captureException($exception);
        });
    }

    /**
     * Adds the exception mechanism to the event.
     *
     * @param Event $event The event object
     */
    protected function addExceptionMechanismToEvent(Event $event): Event
    {
        $exceptions = $event->getExceptions();

        foreach ($exceptions as $exception) {
            $data = [];
            $mechanism = $exception->getMechanism();
            if ($mechanism !== null) {
                $data = $mechanism->getData();
            }

            $exception->setMechanism(new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, false, $data));
        }

        return $event;
    }
}
