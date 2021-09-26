--TEST--
Test that the FatalErrorListenerIntegration integration captures only the errors allowed by the error_types option
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Options;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\SentrySdk;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transportFactory = new class implements TransportFactoryInterface {
    public function create(Options $options): TransportInterface
    {
        return new class implements TransportInterface {
            public function send(Event $event): PromiseInterface
            {
                echo 'Transport called (it should not have been)' . PHP_EOL;

                return new FulfilledPromise(new Response(ResponseStatus::success()));
            }

            public function close(?int $timeout = null): PromiseInterface
            {
                return new FulfilledPromise(true);
            }
        };
    }
};

$options = new Options([
    'error_types' => E_ALL & ~E_ERROR,
    'default_integrations' => false,
    'integrations' => [
        new FatalErrorListenerIntegration(),
    ],
]);

$client = (new ClientBuilder($options))
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

abstract class AbstractTestClass
{
    abstract public function foo(): void;
}

final class TestClass extends AbstractTestClass
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (Sentry\Tests\AbstractTestClass::foo) in %s on line %d
