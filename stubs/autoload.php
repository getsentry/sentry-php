<?php

declare(strict_types=1);

if (!extension_loaded('excimer')) {
    require_once __DIR__ . '/ExcimerLog.stub';
    require_once __DIR__ . '/ExcimerLogEntry.stub';
    require_once __DIR__ . '/ExcimerProfiler.stub';
    require_once __DIR__ . '/ExcimerTimer.stub';
    require_once __DIR__ . '/globals.stub';
}

if (!function_exists('Sentry\\instrument')) {
    require_once __DIR__ . '/SentryTracer.stub';
}
