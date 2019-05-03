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

set_exception_handler(static function (): void {
    echo 'Custom exception handler called' . PHP_EOL;

    throw new \Exception('foo bar baz');
});

ErrorHandler::addExceptionListener(static function (): void {
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
