<?php

declare(strict_types=1);

namespace Sentry\State;

use Sentry\Breadcrumb;
use Sentry\ClientInterface;
use Sentry\NoOpClient;

/**
 * @internal
 */
final class BreadcrumbRecorder
{
    private function __construct()
    {
    }

    /**
     * Records the breadcrumb on the given scope if the client configuration allows it.
     */
    public static function record(ClientInterface $client, IsolationScope $scope, Breadcrumb $breadcrumb): bool
    {
        if ($client instanceof NoOpClient) {
            return false;
        }

        $options = $client->getOptions();
        $maxBreadcrumbs = $options->getMaxBreadcrumbs();

        if ($maxBreadcrumbs <= 0) {
            return false;
        }

        try {
            $breadcrumb = ($options->getBeforeBreadcrumbCallback())($breadcrumb);
        } catch (\Throwable $exception) {
            $options->getLoggerOrNullLogger()->error(\sprintf('The "before_breadcrumb" callback failed with exception: "%s".', $exception->getMessage()));

            return false;
        }

        if ($breadcrumb !== null) {
            $scope->addBreadcrumb($breadcrumb, $maxBreadcrumbs);
        }

        return $breadcrumb !== null;
    }
}
