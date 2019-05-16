--TEST--
Test that requiring a broken class (with a parser error) will not explode during serialization
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Options;
use Sentry\Serializer\Serializer;
use Sentry\State\Hub;
use Sentry\Tests\resources\BrokenClass;
use Sentry\Transport\TransportInterface;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

function testSerialization($value) {
    $serializer = new Serializer(new Options());

    echo $serializer->serialize($value);
}

testSerialization(BrokenClass::class . '::brokenMethod');
echo PHP_EOL;
testSerialization([BrokenClass::class, 'brokenMethod']);

?>
--EXPECT--
Sentry\Tests\resources\BrokenClass::brokenMethod {serialization error}
{serialization error}
