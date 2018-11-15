--TEST--
Even if the error is not catched by the current error_reporting setting, Sentry's error handler respects its own capture
level, and it should be invoked anyway
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ErrorHandler;
use function Sentry\init;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

error_reporting(E_ERROR);

ErrorHandler::register(function () {
    echo 'Callback invoked' . PHP_EOL;
});


echo 'Triggering error' . PHP_EOL;
trigger_error(E_WARNING);

echo 'End'
?>
--EXPECTF--
Triggering error
Callback invoked
End
