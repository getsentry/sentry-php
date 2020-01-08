--TEST--
Test that a thrown exception is captured only once if rethrown from a previous exception handler
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

set_exception_handler(static function (\Exception $exception): void {
    echo 'Custom exception handler called';

    throw $exception;
});

$transportFactory = new class implements TransportFactoryInterface {
    public function create(Options $options): TransportInterface
    {
        return new class implements TransportInterface {
            public function send(Event $event): ?string
            {
                echo 'Transport called' . PHP_EOL;

                return null;
            }
        };
    }
};

$client = ClientBuilder::create([])
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

throw new \Exception('foo bar');
?>
--EXPECTF--
Transport called
Custom exception handler called
Fatal error: Uncaught Exception: foo bar in %s:%d
Stack trace:
%a
