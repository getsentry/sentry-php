--TEST--
Test that the FatalErrorListenerIntegration integration captures only the errors allowed by the `error_types` option
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transport = new class implements TransportInterface {
    public function send(Event $event): ?string
    {
        echo 'Transport called' . PHP_EOL;

        return null;
    }
};

$options = new Options();
$options->setDefaultIntegrations(false);
$options->setIntegrations([
    new FatalErrorListenerIntegration($options),
]);

$client = (new ClientBuilder($options))
    ->setTransport($transport)
    ->getClient();

SentrySdk::bindClient($client);

class FooClass implements \Serializable
{
}

$options->setErrorTypes(E_ALL & ~E_ERROR);

class BarClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\FooClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Transport called
