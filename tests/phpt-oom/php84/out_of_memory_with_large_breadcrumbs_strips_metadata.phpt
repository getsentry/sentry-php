--TEST--
Test that when handling an OOM error with large breadcrumbs, breadcrumb metadata is stripped to prevent secondary OOM during serialization
--SKIPIF--
<?php
if (PHP_VERSION_ID >= 80500) {
    die('skip - only works for PHP 8.4 and below');
}
--INI--
memory_limit=67108864
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Serializer\PayloadSerializerInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

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
        $breadcrumbs = $event->getBreadcrumbs();
        echo 'Breadcrumb count: ' . \count($breadcrumbs) . \PHP_EOL;

        if (\count($breadcrumbs) > 0) {
            $firstBreadcrumb = $breadcrumbs[0];
            echo 'First breadcrumb category: ' . $firstBreadcrumb->getCategory() . \PHP_EOL;
            echo 'First breadcrumb has metadata: ' . (empty($firstBreadcrumb->getMetadata()) ? 'no' : 'yes') . \PHP_EOL;
        }

        $this->payloadSerializer->serialize($event);

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

// Add 100 breadcrumbs with ~100KB metadata each to simulate the real-world scenario
$hub = SentrySdk::getCurrentHub();
$hub->configureScope(function (\Sentry\State\Scope $scope): void {
    for ($i = 0; $i < 100; ++$i) {
        $scope->addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'db.query',
            'SELECT * FROM large_table WHERE id = ?',
            ['bindings' => str_repeat('x', 100 * 1024)]
        ));
    }
});

// Trigger OOM - the remaining memory after breadcrumbs is limited
$array = [];
for ($i = 0; $i < 100000000; ++$i) {
    $array[] = 'sentry';
}
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
Breadcrumb count: 100
First breadcrumb category: db.query
First breadcrumb has metadata: no
Transport called
