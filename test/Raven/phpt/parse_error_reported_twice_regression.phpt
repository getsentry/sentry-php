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

include __DIR__ . '/resources/parseError.php';

?>
--EXPECTF--
Sending message: syntax error, unexpected 'error' (T_STRING)
Sending message of type: ParseError

Parse error: syntax error, unexpected 'error' (T_STRING) in %s/test/Raven/phpt/resources/parseError.php on line 3

Fatal error: Exception thrown without a stack frame in Unknown on line 0
