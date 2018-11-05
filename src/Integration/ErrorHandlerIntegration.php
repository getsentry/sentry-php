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
                $this->getSeverityFromErrorException($exception),
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

    /**
     * Maps the severity of the error to one of the levels supported by the
     * breadcrumbs.
     *
     * @param \ErrorException $exception The exception
     *
     * @return string
     */
    private function getSeverityFromErrorException(\ErrorException $exception): string
    {
        switch ($exception->getSeverity()) {
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_WARNING:
            case E_USER_WARNING:
            case E_RECOVERABLE_ERROR:
                return Breadcrumb::LEVEL_WARNING;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
                return Breadcrumb::LEVEL_CRITICAL;
            case E_USER_ERROR:
                return Breadcrumb::LEVEL_ERROR;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return Breadcrumb::LEVEL_INFO;
            default:
                return Breadcrumb::LEVEL_ERROR;
        }
    }
}
