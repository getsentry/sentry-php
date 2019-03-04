--TEST--
Test catching fatal errors
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\ErrorHandler;
use Sentry\Event;
use Sentry\State\Hub;
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

$client = ClientBuilder::create([])
    ->setTransport($transport)
    ->getClient();

Hub::getCurrent()->bindClient($client);

ErrorHandler::addErrorListener(static function (): void {
    echo 'Error listener called' . PHP_EOL;
});

ErrorHandler::addFatalErrorListener(static function (): void {
    echo 'Fatal error listener called' . PHP_EOL;
});

ErrorHandler::addExceptionListener(static function (): void {
    echo 'Exception listener called (it should not have been)' . PHP_EOL;
});

class TestClass implements \Serializable
{
}
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 2 abstract methods and must therefore be declared abstract or implement the remaining methods (Serializable::serialize, Serializable::unserialize) in %s on line %d
Error listener called
Transport called
Fatal error listener called
