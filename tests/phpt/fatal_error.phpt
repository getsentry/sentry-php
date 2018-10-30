--TEST--
Test catching fatal errors
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ClientBuilder;
use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$client = ClientBuilder::create([
    'dsn' => 'http://public:secret@local.host/1',
    'send_attempts' => 1,
])->getClient();

ErrorHandler::register($client);

register_shutdown_function('register_shutdown_function', function () use ($client) {
    /** @var \Sentry\Transport\HttpTransport $transport */
    $transport = Assert::getObjectAttribute($client, 'transport');

    Assert::assertAttributeEmpty('pendingRequests', $transport);

    echo 'Shutdown function called';
});

class TestClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Shutdown function called
