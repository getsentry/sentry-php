--TEST--
Test catching out of memory fatal error
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ClientBuilder;
use Sentry\ErrorHandler;

ini_set('memory_limit', '20M');

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

    Assert::assertNotNull($client->getLastEvent());
    Assert::assertAttributeEmpty('pendingRequests', $transport);

    echo 'Shutdown function called';
});

$foo = str_repeat('x', 1024 * 1024 * 30);
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Shutdown function called
