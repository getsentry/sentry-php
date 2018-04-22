--TEST--
Test catching out of memory fatal error
--FILE--
<?php

namespace Raven\Tests;

use Raven\ClientBuilder;
use Raven\ErrorHandler;

ini_set('memory_limit', '20M');

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$client = ClientBuilder::create()->getClient();

ErrorHandler::register($client);

$foo = str_repeat('x', 1024 * 1024 * 30);
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d