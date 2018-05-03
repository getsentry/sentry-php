--TEST--
Test catching fatal errors
--FILE--
<?php

namespace Raven\Tests;

use PHPUnit\Framework\Assert;
use Raven\ClientBuilder;
use Raven\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$client = ClientBuilder::create([
    'server' => 'http://public:secret@local.host/1',
    'send_attempts' => 1,
])->getClient();

ErrorHandler::register($client);

register_shutdown_function('register_shutdown_function', function () use ($client) {
    /** @var \Raven\Transport\HttpTransport $transport */
    $transport = Assert::getObjectAttribute($client, 'transport');

    Assert::assertNotNull($client->getLastEvent());
    Assert::assertAttributeEmpty('pendingRequests', $transport);

    echo 'Shutdown function called';
});

class TestClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Raven\Tests\TestClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Shutdown function called