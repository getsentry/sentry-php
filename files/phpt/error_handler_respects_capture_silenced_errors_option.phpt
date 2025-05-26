--TEST--
Test that the error handler ignores silenced errors by default, but it reports them with the appropriate option enabled.
--INI--
error_reporting=E_ALL
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
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

$options = [
    'dsn' => 'http://public@example.com/sentry/1',
    'capture_silenced_errors' => true
];

$client = ClientBuilder::create($options)
    ->setTransport($transport)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

echo 'Triggering silenced error' . PHP_EOL;

@$a++;

$client->getOptions()->setCaptureSilencedErrors(false);

echo 'Triggering silenced error' . PHP_EOL;

@$b++;
?>
--EXPECT--
Triggering silenced error
Transport called
Triggering silenced error
