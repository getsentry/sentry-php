--TEST--
Test that the exception listener gets registered using the deprecated method
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

ErrorHandler::addExceptionListener(static function (): void {
    echo 'Exception listener called';
});

throw new \Exception();
--EXPECTF--
Exception listener called
Fatal error: Uncaught Exception in %s:%d
Stack trace:
%a
