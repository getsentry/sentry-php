--TEST--
Test catching fatal errors
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ErrorHandler;
use function Sentry\init;
use Sentry\State\Hub;use Sentry\Tests\Fixtures\classes\StubErrorListener;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

init([
    'dsn' => 'http://public:secret@local.host/1',
    'send_attempts' => 1,
]);

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
