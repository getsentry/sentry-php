--TEST--
Test catching fatal errors does not capture twice
--FILE--
<?php

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\Spool\MemorySpool;
use Sentry\Transport\SpoolTransport;
use Sentry\ClientBuilder;
use function Sentry\init;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$spool = new MemorySpool();
$transport = new SpoolTransport($spool);

init([], ClientBuilder::create()->setTransport($transport));

register_shutdown_function('register_shutdown_function', function () use ($spool) {
    Assert::assertAttributeCount(1, 'events', $spool);

    echo 'Shutdown function called';
});

\Foo\Bar::baz();
?>
--EXPECTREGEX--
Fatal error: (?:Class 'Foo\\Bar' not found in [^\r\n]+ on line \d+|Uncaught Error: Class 'Foo\\Bar' not found in [^\r\n]+:\d+)
(?:Stack trace:[\s\S]+)?Shutdown function called
