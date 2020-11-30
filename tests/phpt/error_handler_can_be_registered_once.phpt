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

function getHandlerRegistrationCount(callable $setHandlerCallback, callable $restoreHandlerCallback): int
{
    $savedErrorHandlers = [];
    $errorHandler = null;
    $previousErrorHandler = null;

    while (true) {
        $errorHandler = call_user_func($setHandlerCallback, 'var_dump');

        // Restore the error handler that has been popped out from the stack with
        // the line above
        call_user_func($restoreHandlerCallback);

        if (!$errorHandler) {
            break;
        }

        // Restore the next error handler so that we don't reuse it in the next loop
        // iteration
        call_user_func($restoreHandlerCallback);

        if ($errorHandler !== $previousErrorHandler) {
            array_unshift($savedErrorHandlers, $errorHandler);

            $previousErrorHandler = $errorHandler;
        }
    }

    foreach ($savedErrorHandlers as $savedErrorHandler) {
        call_user_func($setHandlerCallback, $savedErrorHandler);
    }

    return count($savedErrorHandlers);
}

var_dump(ErrorHandler::registerOnceErrorHandler() === ErrorHandler::registerOnceErrorHandler());
var_dump(1 === getHandlerRegistrationCount('set_error_handler', 'restore_error_handler'));

var_dump(ErrorHandler::registerOnceExceptionHandler() === ErrorHandler::registerOnceExceptionHandler());
var_dump(1 === getHandlerRegistrationCount('set_exception_handler', 'restore_exception_handler'));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
