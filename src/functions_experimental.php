<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Metrics\Metrics;

/**
 * This is an experimental feature and should neither be used nor considered stable.
 * The API might change at any time without prior warning.
 *
 * @internal
 */
function metrics(): Metrics
{
    return Metrics::getInstance();
}
