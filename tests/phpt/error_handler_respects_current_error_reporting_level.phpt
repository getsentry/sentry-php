--TEST--
Test that the error handler uses the current error_reporting() level.
--INI--
error_reporting=E_ALL
display_errors=Off
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ClientBuilder;
use Sentry\SentrySdk;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$client = ClientBuilder::create([
    'error_types' => null,
    'before_send' => static function () {
        echo 'Before send callback called' . PHP_EOL;

        return null;
    },
])->getClient();

SentrySdk::getCurrentHub()->bindClient($client);

echo 'Triggering E_USER_NOTICE with error reporting on E_ALL' . PHP_EOL;

trigger_error('A notice that should be captured', E_USER_NOTICE);

$old_error_reporting = error_reporting(E_ALL & ~E_USER_NOTICE);

echo 'Triggering E_USER_NOTICE with error reporting on E_ALL & ~E_USER_NOTICE' . PHP_EOL;

trigger_error('A notice that should not be captured', E_USER_NOTICE);

error_reporting($old_error_reporting);

echo 'Triggering E_USER_NOTICE with error reporting on E_ALL again' . PHP_EOL;

trigger_error('A notice that should be captured', E_USER_NOTICE);

?>
--EXPECT--
Triggering E_USER_NOTICE with error reporting on E_ALL
Before send callback called
Triggering E_USER_NOTICE with error reporting on E_ALL & ~E_USER_NOTICE
Triggering E_USER_NOTICE with error reporting on E_ALL again
Before send callback called
