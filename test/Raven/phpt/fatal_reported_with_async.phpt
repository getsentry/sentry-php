--TEST--
Test that, when handling a fatal with async send enabled, we force the async to avoid losing the event
--FILE--
<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/test/bootstrap.php';

$client = new \Raven_Client(array('curl_method' => 'async'));
register_shutdown_function(function () {
    if (constant('RAVEN_CURL_END_REACHED')) {
        echo 'Raven_CurlHandler::join() was called before' . PHP_EOL;
    }
});

$client->install();

ini_set('memory_limit', '8M');
while (TRUE) {
    $a[] = 'b';
}
?>
--EXPECTF--
Fatal error: Allowed memory size %s
Raven_CurlHandler::join() was called before
