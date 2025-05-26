--TEST--
Test that requiring a broken class (with a parser error) will not explode during serialization
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\Options;
use Sentry\Serializer\Serializer;
use Sentry\Tests\Fixtures\code\BrokenClass;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

// issue present itself in backtrace serialization, see:
// - https://github.com/getsentry/sentry-php/pull/818
// - https://github.com/getsentry/sentry-symfony/issues/63#issuecomment-493046411
function testSerialization($value) {
    $serializer = new Serializer(new Options());

    echo json_encode($serializer->serialize($value));
}

testSerialization(BrokenClass::class . '::brokenMethod');
echo PHP_EOL;
testSerialization([BrokenClass::class, 'brokenMethod']);

?>
--EXPECT--
"Sentry\\Tests\\Fixtures\\code\\BrokenClass::brokenMethod"
["Sentry\\Tests\\Fixtures\\code\\BrokenClass","brokenMethod"]
