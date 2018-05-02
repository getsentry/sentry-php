--TEST--
Test that, when handling a fatal, we report it once and only once
--SKIPIF--
<?php if (PHP_VERSION_ID < 70000) die('Skipped: this test makes sense only under PHP 7+'); ?>
--FILE--
<?php

$vendor = __DIR__;
while (! file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor . '/test/bootstrap.php';

error_reporting(E_ALL);
$client = new \Raven_Client();

$client->setSendCallback(function (array $data) {
    echo 'Sending message: ' . $data['exception']['values'][0]['value'] . PHP_EOL;
    echo 'Sending message of type: ' . $data['exception']['values'][0]['type'] . PHP_EOL;

    return false;
});
$client->install();

function iAcceptOnlyArrays(array $array)
{
    return false;
}

iAcceptOnlyArrays('not an array');

?>
--EXPECTF--
Sending message: Argument 1 passed to iAcceptOnlyArrays() must be of the type array, string given%s
Sending message of type: %s

Fatal error: Uncaught TypeError: Argument 1 passed to iAcceptOnlyArrays() must be of the type array, string given%s
Stack trace:
#0 %s
#1 {main}
  thrown in %s
