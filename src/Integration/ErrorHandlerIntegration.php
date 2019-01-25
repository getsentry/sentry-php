<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Breadcrumb;
use Sentry\ErrorHandler;
use Sentry\State\HubInterface;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ErrorHandlerIntegration implements IntegrationInterface
{
    public function bindToHub(HubInterface $hub): IntegrationInterface
    {
        ErrorHandler::register(function (\Throwable $exception) use ($hub): void {
            if ($exception instanceof \ErrorException) {
                $breadcrumb = new Breadcrumb(
                    Breadcrumb::levelFromErrorException($exception),
                    Breadcrumb::TYPE_ERROR,
                    'error_reporting',
                    $exception->getMessage(),
                    [
                        'code' => $exception->getCode(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]
                );

                $hub->addBreadcrumb($breadcrumb);
            }

            $hub->captureException($exception);
        });

        return $this;
    }
}
