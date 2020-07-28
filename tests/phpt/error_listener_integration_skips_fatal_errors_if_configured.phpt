--TEST--
Test that the ErrorListenerIntegration integration ignores the fatal errors if configured to do so
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Options;
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
            public function send(Event $event): ?string
            {
                echo 'Transport called' . PHP_EOL;

                return null;
            }
        };
    }
};

$options = new Options([
    'default_integrations' => false,
    'integrations' => [
        new ErrorListenerIntegration(null, false),
    ],
]);

$client = (new ClientBuilder($options))
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

class FooClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\FooClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
