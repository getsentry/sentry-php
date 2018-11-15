<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Breadcrumb;
use Sentry\ErrorHandler;
use Sentry\State\Hub;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ErrorHandlerIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        ErrorHandler::register(function (\Throwable $exception): void {
            $self = Hub::getCurrent()->getIntegration(self::class);

            if ($self instanceof self) {
                $self->addBreadcrumb($exception);
                $self->captureException($exception);
            }
        });
    }

    /**
     * Captures the exception and sends it to Sentry.
     *
     * @param \Throwable $exception The exception that will be captured by the current client
     */
    private function captureException(\Throwable $exception): void
    {
        Hub::getCurrent()->captureException($exception);
    }

    /**
     * Adds a breadcrumb of the error.
     *
     * @param \Throwable $exception The exception used to create a breadcrumb
     */
    private function addBreadcrumb(\Throwable $exception): void
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
