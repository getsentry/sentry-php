--TEST--
Test that the error handler reports errors set by the `error_types` option and not the `error_reporting` level.
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\SentrySdk;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transportFactory = new class implements TransportFactoryInterface {
    public function create(Options $options): TransportInterface
    {
        return new class implements TransportInterface {
            public function send(Event $event): PromiseInterface
            {
                echo 'Transport called' . PHP_EOL;

                return new FulfilledPromise(new Response(ResponseStatus::success()));
            }

            public function close(?int $timeout = null): PromiseInterface
            {
                return new FulfilledPromise(true);
            }
        };
    }
};

error_reporting(E_ALL & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_ERROR);

$client = ClientBuilder::create(['error_types' => E_ALL & ~E_USER_WARNING])
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

echo 'Triggering E_USER_NOTICE error' . PHP_EOL;

trigger_error('This E_USER_NOTICE which will be reported by Sentry!', E_USER_NOTICE);

echo 'Triggering E_USER_WARNING error' . PHP_EOL;

trigger_error('This E_USER_WARNING won\'t be reported by Sentry because of the error_types option!', E_USER_WARNING);

echo 'Triggering E_USER_ERROR error' . PHP_EOL;

trigger_error('This E_USER_ERROR will be reported by Sentry if on PHP 8 or above!', E_USER_ERROR);

?>
--EXPECT--
Triggering E_USER_NOTICE error
Transport called
Triggering E_USER_WARNING error
Triggering E_USER_ERROR error
Transport called
