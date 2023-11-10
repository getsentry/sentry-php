--TEST--
Test catching out of memory fatal error without increasing memory limit
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
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Fatal error listener called
After OOM memory limit: 67108864
