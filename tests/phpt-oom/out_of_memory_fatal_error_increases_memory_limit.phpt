--TEST--
Test that when handling a out of memory error the memory limit is increased with 5 MiB and the event is serialized and ready to be sent
--INI--
memory_limit=67108864
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$options = new Options([
    'dsn' => 'http://public@example.com/sentry/1',
]);

$transport = new class(new PayloadSerializer($options)) implements TransportInterface {
    private $payloadSerializer;

    public function __construct(PayloadSerializerInterface $payloadSerializer)
    {
        $this->payloadSerializer = $payloadSerializer;
    }

    public function send(Event $event): Result
    {
        $serialized = $this->payloadSerializer->serialize($event);

        echo 'Transport called' . \PHP_EOL;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
};

$options->setTransport($transport);

$client = (new ClientBuilder($options))->getClient();

SentrySdk::init()->bindClient($client);

echo 'Before OOM memory limit: ' . \ini_get('memory_limit');

register_shutdown_function(function () {
    echo 'After OOM memory limit: ' . \ini_get('memory_limit');
});

$array = [];
for ($i = 0; $i < 100000000; ++$i) {
    $array[] = 'sentry';
}
--EXPECTF--
Before OOM memory limit: 67108864
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Transport called
After OOM memory limit: 72351744
