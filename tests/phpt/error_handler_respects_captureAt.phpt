--TEST--
Even if the error is catched by the current error_reporting setting, Sentry's error handler respects its own capture
level, and it should NOT be invoked in this case
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

error_reporting(E_ALL);

$errorHandler = ErrorHandler::register(function () {
    echo 'Callback invoked' . PHP_EOL;
})->captureAt(E_ERROR, true);

echo 'Triggering error';
trigger_error('Triggered error which will be captured by PHP error handler');
echo 'End';
?>
--EXPECTREGEX--
Triggering error
Notice: Triggered error which will be captured by PHP error handler .+
End
