--TEST--
Test that, when handling a fatal with async send enabled, we force the async to avoid losing the event
--FILE--
<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/test/bootstrap.php';
require $vendor.'/vendor/autoload.php';

$dsn = 'https://user:password@sentry.test/123456';
$client = new \Raven_Client($dsn, array('curl_method' => 'async', 'server' => 'sentry.test'));
$pendingEvents = \PHPUnit\Framework\Assert::getObjectAttribute($client, '_pending_events');
$curlHandler = \PHPUnit\Framework\Assert::getObjectAttribute($client, '_curl_handler');
$pendingRequests = \PHPUnit\Framework\Assert::getObjectAttribute($curlHandler, 'requests');

$client->setSendCallback(function () {
    echo 'Sending handled fatal error...' . PHP_EOL;
});

$client->install();

register_shutdown_function(function () use (&$pendingEvents, &$pendingRequests) {
    if (! empty($pendingEvents)) {
        echo 'There are pending events inside the client';
    }

    if (empty($pendingRequests)) {
        echo 'Curl handler successfully emptied';
    } else {
        echo 'There are still queued request inside the Curl Handler';
    }
});

ini_set('memory_limit', '8M');
while (TRUE) {
    $a[] = 'b';
}
?>
--EXPECTF--
Fatal error: Allowed memory size %s
Sending handled fatal error...
Curl handler successfully emptied
