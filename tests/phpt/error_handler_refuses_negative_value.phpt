--TEST--
Test that the error handler is called regardless of the current `error_reporting` setting
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ErrorHandler;
use Sentry\Tests\Fixtures\classes\StubErrorListener;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

try {
    ErrorHandler::registerOnce(-1);
} catch (\InvalidArgumentException $exception) {
    echo 'Exception caught: ';
    echo $exception->getMessage();
}
?>
--EXPECTF--
Exception caught: The $reservedMemorySize argument must be greater than 0.
