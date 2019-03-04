--TEST--
Test catching exceptions
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

set_exception_handler(function ($exception) {
    echo 'Custom exception handler called';
});

ErrorHandler::addErrorListener(static function (): void {
    echo 'Error listener called (it should not have been)' . PHP_EOL;
});

ErrorHandler::addFatalErrorListener(static function (): void {
    echo 'Fatal error listener called (it should not have been)';
});

ErrorHandler::addExceptionListener(static function (): void {
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
