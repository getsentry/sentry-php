<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\Breadcrumb;
use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;
use Sentry\SentrySdk;
use Sentry\State\MutableScope;

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
            $client = SentrySdk::getClient();
            $integration = $client->getIntegration(self::class);

            if ($integration === null) {
                return;
            }

            if (($client->getOptions()->getErrorTypes() & $exception->getSeverity()) === 0) {
                return;
            }

            if (preg_match(self::OOM_MESSAGE_MATCHER, $exception->getMessage()) === 1) {
                // Both scopes contribute breadcrumbs to the event, so both need to be stripped
                // to prevent a secondary OOM while serializing it. The process is terminating.
                self::stripBreadcrumbMetadata(SentrySdk::getGlobalScope());
                self::stripBreadcrumbMetadata(SentrySdk::getIsolationScope());
            }

            $integration->captureException($exception);
        });
    }

    private static function stripBreadcrumbMetadata(MutableScope $scope): void
    {
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
    }
}
