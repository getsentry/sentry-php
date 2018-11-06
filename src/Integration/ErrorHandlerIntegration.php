<?php

namespace Sentry\Integration;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\ErrorHandler;
use Sentry\State\Hub;

final class ErrorHandlerIntegration implements Integration
{
    public function setupOnce(): void
    {
        ErrorHandler::register(function ($exception) {
            $self = Hub::getCurrent()->getIntegration($this);
            if ($self instanceof self) {
                $self->addBreadcrumb($exception);
                $self->captureException($exception);
            }
        });
    }

    /**
     * Captures the exception and sends it to Sentry.
     *
     * @param \ErrorException|\Throwable $exception
     */
    private function captureException($exception): void
    {
        Hub::getCurrent()->captureException($exception);
    }

    /**
     * Adds a breadcrumb of the error.
     *
     * @param \ErrorException|\Throwable $exception
     */
    private function addBreadcrumb($exception): void
    {
        if ($exception instanceof \ErrorException) {
            /* @var \ErrorException $exception */
            Hub::getCurrent()->addBreadcrumb(new Breadcrumb(
                Breadcrumb::levelFromErrorException($exception),
                Breadcrumb::TYPE_ERROR,
                'error_reporting',
                $exception->getMessage(),
                [
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]
            ));
        }
    }
}
