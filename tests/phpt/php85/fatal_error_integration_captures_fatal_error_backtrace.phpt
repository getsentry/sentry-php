--TEST--
Test that the FatalErrorListenerIntegration integration captures fatal errors with their stack trace
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip - only works for PHP 8.5 and above');
}
--INI--
fatal_error_backtraces=1
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\FatalErrorListenerIntegration;
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

        foreach ($event->getExceptions()[0]->getStacktrace()->getFrames() as $frame) {
            echo ($frame->getFunctionName() ?? '{main}') . PHP_EOL;
        }

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
};

$options = new Options([
    'default_integrations' => false,
    'integrations' => [
        new FatalErrorListenerIntegration(),
    ],
]);

$client = (new ClientBuilder($options))
    ->setTransport($transport)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

function inner(): void
{
    final class TestClass implements \JsonSerializable
    {
    }
}

function outer(): void
{
    inner();
}

outer();
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 1 abstract method and must therefore be declared abstract or implement the remaining method (JsonSerializable::jsonSerialize) in %s on line %d
Stack trace:
%A
Transport called
{main}
Sentry\Tests\outer
Sentry\Tests\inner
