<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Event;
use Sentry\ExceptionMechanism;
use Sentry\State\EventCapturer;
use Sentry\State\Scope;

use function Sentry\withIsolationScope;

abstract class AbstractErrorListenerIntegration implements IntegrationInterface
{
    /**
     * @param \Throwable $exception The exception instance
     */
    protected function captureException(\Throwable $exception): void
    {
        withIsolationScope(function (Scope $scope) use ($exception): void {
            $scope->addEventProcessor(\Closure::fromCallable([$this, 'addExceptionMechanismToEvent']));

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
