--TEST--
Test that only the error handler is registered when configured to do so
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

ErrorHandler::registerOnceErrorHandler();

$previousErrorHandler = set_error_handler('var_dump');
$previousExceptionHandler = set_exception_handler('var_dump');

restore_error_handler();
restore_exception_handler();

var_dump(null !== $previousErrorHandler);
var_dump(null !== $previousExceptionHandler);
?>
--EXPECT--
bool(true)
bool(false)
