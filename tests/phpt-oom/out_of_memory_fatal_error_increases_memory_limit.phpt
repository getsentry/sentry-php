--TEST--
Test that when handling a out of memory error the memory limit is increased with 5 MiB and the event is serialized and ready to be sent
--INI--
memory_limit=67108864
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Options;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Serializer\PayloadSerializerInterface;
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
        return new class(new PayloadSerializer($options)) implements TransportInterface {
            private $payloadSerializer;

            public function __construct(PayloadSerializerInterface $payloadSerializer)
            {
                $this->payloadSerializer = $payloadSerializer;
            }

            public function send(Event $event): PromiseInterface
            {
                $serialized = $this->payloadSerializer->serialize($event);

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

$options = new Options([
    'dsn' => 'http://public@example.com/sentry/1',
]);

$client = (new ClientBuilder($options))
    ->setTransportFactory($transportFactory)
    ->getClient();

SentrySdk::init()->bindClient($client);

echo 'Before OOM memory limit: ' . ini_get('memory_limit');

register_shutdown_function(function () {
    echo 'After OOM memory limit: ' . ini_get('memory_limit');
});

$array = [];
for ($i = 0; $i < 100000000; ++$i) {
    $array[] = 'sentry';
}
?>
--EXPECTF--
Before OOM memory limit: 67108864
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Transport called
After OOM memory limit: 72351744
