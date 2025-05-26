--TEST--
Test that an exception thrown from the previous handler is captured
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

set_exception_handler(static function (): void {
    echo 'Custom exception handler called' . PHP_EOL;

    throw new \Exception('foo bar baz');
});

$errorHandler = ErrorHandler::registerOnceErrorHandler();
$errorHandler->addErrorHandlerListener(static function (): void {
    echo 'Error listener called (it should not have been)' . PHP_EOL;
});

$errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
$errorHandler->addFatalErrorHandlerListener(static function (): void {
    echo 'Fatal error listener called (it should not have been)' . PHP_EOL;
});

$errorHandler = ErrorHandler::registerOnceExceptionHandler();
$errorHandler->addExceptionHandlerListener(static function (): void {
    echo 'Exception listener called' . PHP_EOL;
});

throw new \Exception('foo bar');
?>
--EXPECTF--
Exception listener called
Custom exception handler called
Exception listener called

Fatal error: Uncaught Exception: foo bar baz in %s:%d
Stack trace:
%a
