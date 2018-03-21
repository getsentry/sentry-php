--TEST--
Test that, when handling a fatal, we report it once and only once
--SKIPIF--
<?php if (PHP_VERSION_ID < 70000) die('This test makes sense only under PHP 7+'); ?>
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
Sending message: Return value of thisiswrong() must be of the type float, string returned
Sending message of type: TypeError

Fatal error: Uncaught TypeError: Return value of thisiswrong() must be of the type float, string returned in %a
Stack trace:
#0 %s
#1 {main}
  thrown in - on line %s
