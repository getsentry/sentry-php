--TEST--
Test emptying spool transport
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\SentrySdk;
use Sentry\Spool\MemorySpool;
use Sentry\Transport\SpoolTransport;
use Sentry\Transport\NullTransport;
use Sentry\ClientBuilder;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$spool = new MemorySpool();
$transport = new SpoolTransport($spool);
$nullTransport = new NullTransport();

$client = ClientBuilder::create()
    ->setTransport($transport)
    ->getClient();

SentrySdk::bindClient($client);

register_shutdown_function('register_shutdown_function', function () use ($spool, $nullTransport) {
    Assert::assertAttributeCount(1, 'events', $spool);

    $spool->flushQueue($nullTransport);

    Assert::assertAttributeCount(0, 'events', $spool);

    echo 'Shutdown function called';
});

\Foo\Bar::baz();
?>
--EXPECTF--
Fatal error: Uncaught Error: Class '%s' not found in %s:%d
Stack trace:
%a
Shutdown function called
