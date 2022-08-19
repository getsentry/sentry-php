--TEST--
Test catching fatal errors
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Sentry\ClientBuilder;
use Sentry\ErrorHandler;
use Sentry\Event;
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

$options = [
    'dsn' => 'http://public@example.com/sentry/1',
];

$client = ClientBuilder::create($options)
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

$errorHandler = ErrorHandler::registerOnceErrorHandler();
$errorHandler->addErrorHandlerListener(static function (): void {
    echo 'Error listener called (it should not have been)' . PHP_EOL;
});

$errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
$errorHandler->addFatalErrorHandlerListener(static function (): void {
    echo 'Fatal error listener called' . PHP_EOL;
});

$errorHandler = ErrorHandler::registerOnceExceptionHandler();
$errorHandler->addExceptionHandlerListener(static function (): void {
    echo 'Exception listener called (it should not have been)' . PHP_EOL;
});

final class TestClass implements \JsonSerializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (JsonSerializable::jsonSerialize) in %s on line %d
Transport called
Fatal error listener called
