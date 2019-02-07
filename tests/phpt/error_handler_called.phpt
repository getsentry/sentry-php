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

error_reporting(E_ERROR);

ErrorHandler::addErrorListener(new StubErrorListener(function () {
    echo 'Callback invoked' . PHP_EOL;
}));

echo 'Triggering error' . PHP_EOL;
trigger_error(E_WARNING);
echo 'End'
?>
--EXPECTF--
Triggering error
Callback invoked
End
