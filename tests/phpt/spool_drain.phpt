--TEST--
Test emptying spool transport
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\Spool\MemorySpool;
use Sentry\Transport\SpoolTransport;
use Sentry\Transport\NullTransport;
use Sentry\ClientBuilder;
use Sentry\State\Hub;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$spool = new MemorySpool();
$transport = new SpoolTransport($spool);
$nullTransport = new NullTransport();

$builder = ClientBuilder::create()->setTransport($transport);

Hub::getCurrent()->bindClient($builder->getClient());

register_shutdown_function('register_shutdown_function', function () use ($spool, $nullTransport) {
    Assert::assertAttributeCount(1, 'events', $spool);

    $spool->flushQueue($nullTransport);

    Assert::assertAttributeCount(0, 'events', $spool);

    echo 'Shutdown function called';
});

\Foo\Bar::baz();
?>
--EXPECTREGEX--
Fatal error: (?:Class 'Foo\\Bar' not found in [^\r\n]+ on line \d+|Uncaught Error: Class 'Foo\\Bar' not found in [^\r\n]+:\d+)
(?:Stack trace:[\s\S]+)?Shutdown function called
