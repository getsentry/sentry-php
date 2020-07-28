--TEST--
Test that the ErrorListenerIntegration integration captures only the errors allowed by the `error_types` options
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
                echo 'Transport called';

                return null;
            }
        };
    }
};

$options = new Options([
    'error_types' => E_ALL & ~E_USER_WARNING,
    'default_integrations' => false,
    'integrations' => [
        new ErrorListenerIntegration(),
    ],
]);

$client = (new ClientBuilder($options))
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

trigger_error('Error thrown', E_USER_NOTICE);
trigger_error('Error thrown', E_USER_WARNING);
?>
--EXPECTF--
Transport called
Notice: Error thrown in %s on line %d

Warning: Error thrown in %s on line %d
