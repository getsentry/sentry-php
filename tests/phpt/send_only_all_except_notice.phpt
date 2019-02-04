--TEST--
Test catching fatal errors
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ClientBuilder;
use function Sentry\init;
use Sentry\State\Hub;
use Sentry\Tests\Fixtures\classes\StubTransport;
use Sentry\Transport\HttpTransport;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$options = [
    'dsn' => 'http://public:secret@local.host/1',
    'send_attempts' => 1,
    'error_types' => E_ALL & ~E_USER_NOTICE
];
init($options);

error_reporting(E_ALL);

$stubTransport = new StubTransport();
$client = ClientBuilder::create($options)
    ->setTransport($stubTransport)
    ->getClient();

$hub = Hub::getCurrent();
$hub->bindClient($client);

trigger_error('Cannot divide by zero', E_USER_NOTICE);

Assert::assertEmpty($stubTransport->getEvents());

trigger_error('Cannot divide by zero', E_USER_WARNING);

Assert::assertCount(1, $stubTransport->getEvents());
$event = $stubTransport->getLastSent();
Assert::assertSame($event->getId(), $hub->getLastEventId());

echo 'All assertions executed';
?>
--EXPECTREGEX--
Notice: Cannot divide by zero in .* on line \d+

Warning: Cannot divide by zero in .* on line \d+
All assertions executed
