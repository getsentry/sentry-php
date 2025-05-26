--TEST--
Test that the value returned by a previous error handler is used to decide whether to invoke the native PHP handler
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

$returnValue = true;

set_error_handler(static function () use (&$returnValue) {
    return $returnValue;
});

ErrorHandler::registerOnceErrorHandler();

echo 'Triggering error (shouldn\'t be displayed)' . PHP_EOL;

trigger_error('Error thrown', E_USER_WARNING);

$returnValue = false;

echo 'Triggering error (should be displayed)' . PHP_EOL;

trigger_error('Error thrown', E_USER_WARNING);

$returnValue = 'foo bar';

echo 'Triggering error (shouldn\'t be displayed)' . PHP_EOL;

trigger_error('Error thrown', E_USER_WARNING);

echo 'End of script reached';
?>
--EXPECTF--
Triggering error (shouldn't be displayed)
Triggering error (should be displayed)

Warning: Error thrown in %s on line %d
Triggering error (shouldn't be displayed)
End of script reached
