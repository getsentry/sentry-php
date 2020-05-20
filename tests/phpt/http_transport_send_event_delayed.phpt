--TEST--
Test that the HttpTransport transport delays the event sending until the shutdown
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Promise\RejectedPromise;
use Psr\Http\Message\RequestInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\Transport\HttpTransport;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$httpClientInvokationCount = 0;
$httpClient = new class ($httpClientInvokationCount) implements HttpAsyncClientInterface {
    private $httpClientInvokationCount;

    public function __construct(int &$httpClientInvokationCount)
    {
        $this->httpClientInvokationCount = &$httpClientInvokationCount;
    }

    public function sendAsyncRequest(RequestInterface $request)
    {
        ++$this->httpClientInvokationCount;

        return new RejectedPromise(new \Exception());
    }
};

$transport = new HttpTransport(
    new Options(['dsn' => 'http://public@example.com/sentry/1']),
    $httpClient,
    MessageFactoryDiscovery::find()
);

$transport->send(new Event());

var_dump($httpClientInvokationCount);

register_shutdown_function('register_shutdown_function', static function () use (&$httpClientInvokationCount): void {
    var_dump($httpClientInvokationCount);
});
?>
--EXPECT--
int(0)
int(1)
