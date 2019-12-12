--TEST--
Test emptying spool transport
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\Assert;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Spool\MemorySpool;
use Sentry\Spool\SpoolInterface;
use Sentry\Transport\NullTransport;
use Sentry\Transport\SpoolTransport;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$spool = new MemorySpool();
$nullTransport = new NullTransport();

$transportFactory = new class($spool) implements TransportFactoryInterface {
    private $spool;

    public function __construct(SpoolInterface $spool)
    {
        $this->spool = $spool;
    }

    public function create(Options $options): TransportInterface
    {
        return new SpoolTransport($this->spool);
    }
};

$client = ClientBuilder::create()
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

register_shutdown_function('register_shutdown_function', static function () use ($spool, $nullTransport): void {
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
