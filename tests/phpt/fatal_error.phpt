--TEST--
Test catching fatal errors
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use function Sentry\init;
use Sentry\State\Hub;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

init([
    'dsn' => 'http://public:secret@local.host/1',
    'send_attempts' => 1,
]);

register_shutdown_function('register_shutdown_function', function () {
    $client = Hub::getCurrent()->getClient();

    /** @var \Sentry\Transport\HttpTransport $transport */
    $transport = Assert::getObjectAttribute($client, 'transport');

    Assert::assertAttributeEmpty('pendingRequests', $transport);
    Assert::assertNotNull(Hub::getCurrent()->getLastEventId());

    echo 'Shutdown function called';
});

class TestClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Shutdown function called
