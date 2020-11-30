--TEST--
Test that the exception being handled is captured only once even if it's
rethrown from a previous exception handler
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

set_exception_handler(static function (\Throwable $exception): void {
    echo 'Custom exception handler called' . PHP_EOL;

    throw $exception;
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

Fatal error: Uncaught Exception: foo bar in %s:%d
Stack trace:
%a
