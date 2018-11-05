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
            if ($self = Hub::getCurrent()->getIntegration($this)) {
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

                Hub::getCurrent()->captureException($exception);
            }
        });
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
