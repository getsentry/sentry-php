--TEST--
Test catching fatal errors
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ErrorHandler;
use Sentry\Tests\Fixtures\classes\StubErrorListener;
use function Sentry\init;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

init();

ErrorHandler::addErrorListener(new StubErrorListener(function () {
    echo 'Listener called';
}));

class TestClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Listener called
