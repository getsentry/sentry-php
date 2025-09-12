--TEST--
Test that the error handler reports errors set by the `error_types` option and not the `error_reporting` level.
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
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

error_reporting(E_ALL & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_USER_ERROR);

$options = [
    'dsn' => 'http://public@example.com/sentry/1',
    'error_types' => E_ALL & ~E_USER_WARNING,
];

$client = ClientBuilder::create($options)
    ->setTransport($transport)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

echo 'Triggering E_USER_NOTICE error' . PHP_EOL;

trigger_error('Error thrown', E_USER_NOTICE);

echo 'Triggering E_USER_WARNING error (it should not be reported to Sentry due to error_types option)' . PHP_EOL;

trigger_error('Error thrown', E_USER_WARNING);

echo 'Triggering E_USER_ERROR error (unsilenceable on PHP8)' . PHP_EOL;

if (PHP_VERSION_ID >= 80400) {
    // Silence a deprecation notice on PHP 8.4
    // https://wiki.php.net/rfc/deprecations_php_8_4#deprecate_passing_e_user_error_to_trigger_error
    @trigger_error('Error thrown', E_USER_ERROR);
} else {
    trigger_error('Error thrown', E_USER_ERROR);
}
?>
--EXPECT--
Triggering E_USER_NOTICE error
Transport called
Triggering E_USER_WARNING error (it should not be reported to Sentry due to error_types option)
Triggering E_USER_ERROR error (unsilenceable on PHP8)
Transport called
