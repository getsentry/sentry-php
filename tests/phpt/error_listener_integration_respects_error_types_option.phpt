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
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transport = new class implements TransportInterface {
    public function send(Event $event): ?string
    {
        echo 'Transport called';

        return null;
    }
};

$options = new Options();
$options->setErrorTypes(E_ALL & ~E_USER_WARNING);
$options->setDefaultIntegrations(false);
$options->setIntegrations([
    new ErrorListenerIntegration($options),
]);

$client = (new ClientBuilder($options))
    ->setTransport($transport)
    ->getClient();

SentrySdk::bindClient($client);

trigger_error('Error thrown', E_USER_NOTICE);
trigger_error('Error thrown', E_USER_WARNING);
?>
--EXPECTF--
Transport called
Notice: Error thrown in %s on line %d

Warning: Error thrown in %s on line %d
