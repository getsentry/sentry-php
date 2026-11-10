--TEST--
Test that the fatal error handler uses the backtrace PHP provides for fatal errors
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip - only works for PHP 8.5 and above');
}
--INI--
fatal_error_backtraces=1
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
$errorHandler->addFatalErrorHandlerListener(static function (FatalErrorException $exception): void {
    echo 'Fatal error listener called' . PHP_EOL;

    // PHPUnit runs the test through a `require` when it collects code
    // coverage, which adds an outermost entry to the backtrace
    foreach (\array_slice($exception->getTrace(), 0, 2) as $frame) {
        echo $frame['function'] . PHP_EOL;
    }
});

function inner(): void
{
    final class TestClass implements \JsonSerializable
    {
    }
}

function outer(): void
{
    inner();
}

outer();
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 1 abstract method and must therefore be declared abstract or implement the remaining method (JsonSerializable::jsonSerialize) in %s on line %d
Stack trace:
%A
Fatal error listener called
Sentry\Tests\inner
Sentry\Tests\outer
