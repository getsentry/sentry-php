<?php

declare(strict_types=1);

if (extension_loaded('excimer')) {
    return;
}

require_once __DIR__ . '/ExcimerLog.stub';
require_once __DIR__ . '/ExcimerLogEntry.stub';
require_once __DIR__ . '/ExcimerProfiler.stub';
require_once __DIR__ . '/ExcimerTimer.stub';
require_once __DIR__ . '/globals.stub';
