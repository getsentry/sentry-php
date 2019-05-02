--TEST--
Test catching fatal errors
--INI--
errore_reporting=E_ALL
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\Tests\Fixtures\classes\StubTransport;
use function Sentry\init;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$options = [
    'error_types' => E_ALL & ~E_USER_NOTICE
];

init($options);

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
