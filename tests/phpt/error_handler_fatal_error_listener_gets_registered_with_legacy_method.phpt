--TEST--
Test that the fatal error listener gets registered using the deprecated method
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

ErrorHandler::addFatalErrorListener(static function (): void {
    echo 'Fatal error listener called' . PHP_EOL;
});

class TestClass implements \Serializable
{
}
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Fatal error listener called
