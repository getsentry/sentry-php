--TEST--
Test that the error handler throws an error when trying to reserve a negative amount of memory
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

ErrorHandler::registerOnceFatalErrorHandler(-1);
?>
--EXPECTF--
Fatal error: Uncaught InvalidArgumentException: The $reservedMemorySize argument must be greater than 0. in %s:%d
Stack trace:
%a
