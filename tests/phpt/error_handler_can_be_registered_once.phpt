--TEST--
Test that the error handler is registered only once regardless of how many times it's instantiated
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

var_dump(ErrorHandler::registerOnce() === ErrorHandler::registerOnce());

$savedErrorHandlers = [];
$errorHandler = null;
$previousErrorHandler = null;

while (true) {
    $errorHandler = set_error_handler('var_dump');

    // Restore the error handler that has been popped out from the stack with
    // the line above
    restore_error_handler();

    if (!$errorHandler) {
        break;
    }

    // Restore the next error handler so that we don't reuse it in the next loop
    // iteration
    restore_error_handler();

    if ($errorHandler !== $previousErrorHandler) {
        array_unshift($savedErrorHandlers, $errorHandler);

        $previousErrorHandler = $errorHandler;
    }
}

foreach ($savedErrorHandlers as $savedErrorHandler) {
    set_error_handler($savedErrorHandler);
}

var_dump(1 === count($savedErrorHandlers));
?>
--EXPECTF--
bool(true)
bool(true)
