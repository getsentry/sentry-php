--TEST--
Test catching out of memory fatal error that is being serialized and sent to Sentry
--INI--
memory_limit=128M
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

$array = [];
for ($i = 0; $i < 100000000; ++$i) {
    $array[] = str_repeat('a', 1024);
}
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Transport called
