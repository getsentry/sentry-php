<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Metrics\Metrics;

function metrics(): Metrics
{
    return Metrics::getInstance();
}
