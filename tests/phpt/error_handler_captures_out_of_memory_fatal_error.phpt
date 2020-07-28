--TEST--
Test catching out of memory fatal error
--INI--
memory_limit=20M
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
    echo 'Error listener called' . PHP_EOL;
});

$errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
$errorHandler->addFatalErrorHandlerListener(static function (): void {
    echo 'Fatal error listener called' . PHP_EOL;
});

$errorHandler = ErrorHandler::registerOnceExceptionHandler();
$errorHandler->addExceptionHandlerListener(static function (): void {
    echo 'Exception listener called' . PHP_EOL;
});

$foo = str_repeat('x', 1024 * 1024 * 30);
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Error listener called
Fatal error listener called
