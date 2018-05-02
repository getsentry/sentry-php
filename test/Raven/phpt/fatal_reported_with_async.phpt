--TEST--
Test that, when handling a fatal with async send enabled, we force the async to avoid losing the event
--SKIPIF--
<?php if (PHP_VERSION_ID >= 50400 && PHP_VERSION_ID < 50600) die('Skipped: this fails under PHP 5.4/5.5, we cannot fix it'); ?>
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
// doing this to avoid autoload-driver failures during the error handling
$pendingEvents = \PHPUnit\Framework\Assert::getObjectAttribute($client, '_pending_events');
$curlHandler = \PHPUnit\Framework\Assert::getObjectAttribute($client, '_curl_handler');
$pendingRequests = \PHPUnit\Framework\Assert::getObjectAttribute($curlHandler, 'requests');

$client->setSendCallback(function () {
    echo 'Sending handled fatal error...' . PHP_EOL;
});

$client->install();

register_shutdown_function(function () use (&$client) {
    $pendingEvents = \PHPUnit\Framework\Assert::getObjectAttribute($client, '_pending_events');
    $curlHandler = \PHPUnit\Framework\Assert::getObjectAttribute($client, '_curl_handler');
    $pendingRequests = \PHPUnit\Framework\Assert::getObjectAttribute($curlHandler, 'requests');

    if (! empty($pendingEvents)) {
        echo 'There are pending events inside the client';
    }

    if (empty($pendingRequests)) {
        echo 'Curl handler successfully emptied';
    } else {
        echo 'There are still queued request inside the Curl Handler';
    }
});

trigger_error('Fatal please!', E_USER_ERROR);
?>
--EXPECTF--
Sending handled fatal error...

Fatal error: Fatal please! in %s on line %d
Curl handler successfully emptied
