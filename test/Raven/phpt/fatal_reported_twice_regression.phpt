--TEST--
Test that, when handling a fatal, we report it once and only once
--FILE--
<?php

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/test/bootstrap.php';
require_once __DIR__.'/../../DummyRaven/Dummy_Raven_Client.php';

error_reporting(E_ALL);
$client = new \Dummy_Raven_Client('http://public:secret@example.com/1');
set_error_handler(function () use ($client) {
    echo 'Previous error handler is called' . PHP_EOL;
    echo 'Error is ' . ($client->getLastEventID() !== null ? 'reported correctly' : 'NOT reported');
});

set_exception_handler(function () {
    echo 'This should not be called';
});

$client->install();

require 'inexistent_file.php';
?>
--EXPECTF--
Previous error handler is called
Error is reported correctly
Fatal error: %a
