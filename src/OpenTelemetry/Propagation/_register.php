<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Registry;
use Sentry\OpenTelemetry\Propagation\SentryPropagator;

if (!class_exists(Registry::class)) {
    return;
}

Registry::registerTextMapPropagator('sentry', SentryPropagator::getInstance());
