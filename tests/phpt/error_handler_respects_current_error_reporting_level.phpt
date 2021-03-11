--TEST--
Test that the error handler uses the current error_reporting() level.
--INI--
error_reporting=E_ALL
display_errors=Off
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

$client = ClientBuilder::create(['error_types' => null])
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

echo 'Triggering E_USER_NOTICE with error reporting on E_ALL' . PHP_EOL;

trigger_error('A notice that should be captured', E_USER_NOTICE);

$old_error_reporting = error_reporting(E_ALL & ~E_USER_NOTICE);

echo 'Triggering E_USER_NOTICE with error reporting on E_ALL & ~E_USER_NOTICE' . PHP_EOL;

trigger_error('A notice that should not be captured', E_USER_NOTICE);

error_reporting($old_error_reporting);

echo 'Triggering E_USER_NOTICE with error reporting on E_ALL again' . PHP_EOL;

trigger_error('A notice that should be captured', E_USER_NOTICE);

?>
--EXPECT--
Triggering E_USER_NOTICE with error reporting on E_ALL
Transport called
Triggering E_USER_NOTICE with error reporting on E_ALL & ~E_USER_NOTICE
Triggering E_USER_NOTICE with error reporting on E_ALL again
Transport called
