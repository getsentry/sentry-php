--TEST--
Test that the error handler ignores silenced errors by default, but it reports them with the appropriate option enabled.
--SKIPIF--
<?php
if (\PHP_MAJOR_VERSION < 8) {
    die('Skip on PHP < 8 because it\'s not applicable.');
}
?>
--INI--
error_reporting=E_ALL
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

error_reporting(E_ALL & ~E_USER_ERROR);

$client = ClientBuilder::create(['error_types' => E_ALL, 'capture_silenced_errors' => false])
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

echo 'Triggering "silenced" E_USER_ERROR error' . PHP_EOL;

@trigger_error('This E_USER_ERROR cannot be silenced', E_USER_ERROR);
?>
--EXPECT--
Triggering "silenced" E_USER_ERROR error
Transport called
