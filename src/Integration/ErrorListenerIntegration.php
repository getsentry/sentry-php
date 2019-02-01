<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\ErrorListenerInterface;
use Sentry\State\Hub;

/**
 * This integration hooks into the global error handlers and emits events to
 * Sentry.
 */
final class ErrorListenerIntegration implements IntegrationInterface, ErrorListenerInterface
{
    public function setupOnce(): void
    {
        ErrorHandler::addErrorListener($this);
    }

    public function __invoke(\ErrorException $error): void
    {
        $client = Hub::getCurrent()->getClient();
        
        if ($client) {
            $client->captureException($error);
        }
    }
}
