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
    'error_types' => E_USER_WARNING
]);

error_reporting(E_ALL);

$client = Hub::getCurrent()->getClient();

/** @var \Sentry\Transport\HttpTransport $transport */
$transport = Assert::getObjectAttribute($client, 'transport');

trigger_error("Cannot divide by zero", E_USER_ERROR);

Assert::assertAttributeEmpty('pendingRequests', $transport);
Assert::assertNull(Hub::getCurrent()->getLastEventId());

trigger_error("Cannot divide by zero", E_USER_WARNING);

Assert::assertAttributeNotEmpty('pendingRequests', $transport);
Assert::assertNotNull(Hub::getCurrent()->getLastEventId());
?>
--EXPECTF--
Fatal error: Cannot divide by zero in Standard input code on line 30
