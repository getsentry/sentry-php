--TEST--
Test that the ErrorListenerIntegration integration captures only the errors allowed by the `error_types` options
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
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$transport = new class implements TransportInterface {
    public function send(Event $event): Result
    {
        echo 'Transport called' . PHP_EOL;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
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
    ->setTransport($transport)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

trigger_error('Error thrown', E_USER_NOTICE);
trigger_error('Error thrown', E_USER_WARNING);
?>
--EXPECTF--
Transport called

Notice: Error thrown in %s on line %d

Warning: Error thrown in %s on line %d
