--TEST--
Test catching out of memory fatal error
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ErrorHandler;
use Sentry\Event;
use Sentry\Severity;

ini_set('memory_limit', '20M');

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

ErrorHandler::addErrorListener(static function (): void {
    echo 'Error listener called' . PHP_EOL;
});

ErrorHandler::addFatalErrorListener(static function (): void {
    echo 'Fatal error listener called' . PHP_EOL;
});

ErrorHandler::addExceptionListener(static function (): void {
    echo 'Exception listener called' . PHP_EOL;
});

$foo = str_repeat('x', 1024 * 1024 * 30);
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Error listener called
Fatal error listener called
