--TEST--
Test that serializing a pseudo-callable that doesn't exist (like an array of 2 strings) doesn't brake,
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

function testSerialization() {
    $serializer = new Serializer(new Options());

    echo json_encode($serializer->serialize(['FakeClass', 'fakeMethod']));
}

testSerialization();
echo PHP_EOL;
$brokenAutoloader = function (string $classname): void {
    throw new \RuntimeException('Autoloader throws while loading ' . $classname);
};
spl_autoload_register($brokenAutoloader, true, true);

testSerialization();

?>
--EXPECT--
["FakeClass","fakeMethod"]
["FakeClass","fakeMethod"]
