<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\ExceptionListenerInterface;
use Sentry\State\Hub;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ExceptionListenerIntegration implements IntegrationInterface, ExceptionListenerInterface
{
    public function setupOnce(): void
    {
        ErrorHandler::addExceptionListener($this);
    }

    public function __invoke(\Throwable $throwable): void
    {
        $client = Hub::getCurrent()->getClient();

        if ($client) {
            $client->captureException($throwable);
        }
    }
}
