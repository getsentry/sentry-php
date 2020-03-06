--TEST--
Test that the handler captures the thrown exception when no previous handler is set
and that the details of the exception are printed on the screen
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

Fatal error: Uncaught Exception: foo bar in %s:%d
Stack trace:
%a
