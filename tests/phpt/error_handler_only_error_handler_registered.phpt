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

$previousErrorHandler = set_error_handler('print_r');
$previousExceptionHandler = set_exception_handler('print_r');

restore_error_handler();
restore_exception_handler();

if (null !== $previousErrorHandler) {
    echo 'Previous error handler is present' . PHP_EOL;
}

if (null === $previousExceptionHandler) {
    echo 'Previous exception handler is NOT present' . PHP_EOL;
}
?>
--EXPECT--
Previous error handler is present
Previous exception handler is NOT present
