<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\State\Hub;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ExceptionListenerIntegration implements IntegrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        ErrorHandler::addExceptionListener(static function (\Throwable $throwable): void {
            Hub::getCurrent()->captureException($throwable);
        });
    }
}
