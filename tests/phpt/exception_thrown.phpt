--TEST--
Test throwing exception in custom exception handler
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

set_exception_handler(function ($exception) {
    echo 'custom exception handler called';

    throw new \Exception('bar foo');
});

$client = ClientBuilder::create()->getClient();

ErrorHandler::register($client);

throw new \Exception('foo bar');
?>
--EXPECTREGEX--
custom exception handler called
Fatal error: (Uncaught Exception: bar foo|Uncaught exception 'Exception' with message 'bar foo') in [^\r\n]+:\d+
Stack trace:
.+
