--TEST--
Test catching fatal errors does not capture twice
--FILE--
<?php

namespace Raven\Tests;

use PHPUnit\Framework\Assert;
use Raven\ClientBuilder;
use Raven\ErrorHandler;
use Raven\Spool\MemorySpool;
use Raven\Transport\SpoolTransport;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$spool = new MemorySpool();
$transport = new SpoolTransport($spool);

$client = ClientBuilder::create()
    ->setTransport($transport)
    ->getClient();

ErrorHandler::register($client);

register_shutdown_function('register_shutdown_function', function () use ($spool) {
    Assert::assertAttributeCount(1, 'events', $spool);

    echo 'Shutdown function called';
});

\Foo\Bar::baz();
?>
--EXPECTREGEX--
Fatal error: (?:Class 'Foo\\Bar' not found in [^\r\n]+ on line \d+|Uncaught Error: Class 'Foo\\Bar' not found in [^\r\n]+:\d+)
(?:Stack trace:[\s\S]+)?Shutdown function called