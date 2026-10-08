<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Breadcrumb;
use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/**
 * This integration hooks into the error handler and captures fatal errors.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class FatalErrorListenerIntegration extends AbstractErrorListenerIntegration
{
    /**
     * Intentionally looser than ErrorHandler::OOM_MESSAGE_MATCHER — matches the
     * prefixed FatalErrorException message and needs no capture groups.
     */
    private const OOM_MESSAGE_MATCHER = '/Allowed memory size of \d+ bytes exhausted/';

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
        $errorHandler->addFatalErrorHandlerListener(static function (FatalErrorException $exception): void {
            $currentHub = SentrySdk::getCurrentHub();
            $integration = $currentHub->getIntegration(self::class);
            $client = $currentHub->getClient();

            // The client bound to the current hub, if any, could not have this
            // integration enabled. If this is the case, bail out
            if ($integration === null || $client === null) {
                return;
            }

            if (!($client->getOptions()->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            if (preg_match(self::OOM_MESSAGE_MATCHER, $exception->getMessage()) === 1) {
                $currentHub->configureScope(static function (Scope $scope): void {
                    $strippedBreadcrumbs = array_map(static function (Breadcrumb $breadcrumb): Breadcrumb {
                        return new Breadcrumb(
                            $breadcrumb->getLevel(),
                            $breadcrumb->getType(),
                            $breadcrumb->getCategory(),
                            $breadcrumb->getMessage(),
                            [],
                            $breadcrumb->getTimestamp()
                        );
                    }, $scope->getBreadcrumbs());

                    $scope->clearBreadcrumbs();

                    foreach ($strippedBreadcrumbs as $breadcrumb) {
                        $scope->addBreadcrumb($breadcrumb, \count($strippedBreadcrumbs));
                    }
                });
            }

            $integration->captureException($currentHub, $exception);
        });
    }
}
