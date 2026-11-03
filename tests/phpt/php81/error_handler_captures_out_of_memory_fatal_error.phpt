--TEST--
Test catching out of memory fatal error without increasing memory limit
--SKIPIF--
<?php
if (PHP_VERSION_ID >= 80200) {
    die('skip - only works for PHP 8.1 and below');
}

// Some builds of PHP 8.1 and below no longer run the shutdown functions after an
// out of memory error, so the output after the fatal error is not asserted
--INI--
memory_limit=67108864
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

$errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
$errorHandler->addFatalErrorHandlerListener(static function (): void {
    echo 'Fatal error listener called' . PHP_EOL;

    echo 'After OOM memory limit: ' . ini_get('memory_limit');
});

$errorHandler->setMemoryLimitIncreaseOnOutOfMemoryErrorInBytes(null);

echo 'Before OOM memory limit: ' . ini_get('memory_limit');

$foo = str_repeat('x', 1024 * 1024 * 1024);
?>
--EXPECTF--
Before OOM memory limit: 67108864
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d%A
