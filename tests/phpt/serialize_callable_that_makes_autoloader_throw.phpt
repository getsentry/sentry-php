--TEST--
Test that serializing a pseudo-callable that doesn't exist (like an array of 2 strings) doesn't brake
when inside an application with an autoloader that throws, like Yii or Magento (see #1112)
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

function testSerialization(int $depth = 3) {
    $serializer = new Serializer(new Options(), $depth);

    echo json_encode($serializer->serialize(['FakeClass', 'fakeMethod']));
    echo PHP_EOL;
}

testSerialization();
$brokenAutoloader = function (string $classname): void {
    throw new \RuntimeException('Autoloader throws while loading ' . $classname);
};

spl_autoload_register($brokenAutoloader, true, true);

try {
    testSerialization();
    testSerialization(0);
} finally {
    spl_autoload_unregister($brokenAutoloader);
}
?>
--EXPECT--
["FakeClass","fakeMethod"]
["FakeClass","fakeMethod"]
"Array of length 2"
