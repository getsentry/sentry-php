--TEST--
Test that the error handler is not called regardless of the current
`error_reporting` setting if its own `captureAt` configuration doesn't match
the level of the thrown error.
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

error_reporting(E_ERROR);

ErrorHandler::register(function () {
   echo 'Callback invoked' . PHP_EOL;
}, function () {
   echo 'Callback not invoked' . PHP_EOL;
});

echo 'Triggering error' . PHP_EOL;
trigger_error(E_WARNING);
echo 'End'
?>
--EXPECTF--
Triggering error
Callback invoked
End
