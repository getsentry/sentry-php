<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;
use Sentry\SentrySdk;

/**
 * This integration hooks into the error handler and captures fatal errors.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class FatalErrorListenerIntegration extends AbstractErrorListenerIntegration
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
        $errorHandler->addFatalErrorHandlerListener(static function (FatalErrorException $exception): void {
            $client = SentrySdk::getClient();
            $integration = $client->getIntegration(self::class);

            if ($integration === null) {
                return;
            }

            if (!($client->getOptions()->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            $integration->captureException($exception);
        });
    }
}
