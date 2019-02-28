--TEST--
Test that the error handler ignores silenced errors
--FILE--
<?php

namespace Sentry\Tests;

use function Sentry\init;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

init([
    'before_send' => function () {
        echo 'Event captured' . PHP_EOL;
    }    
]);

echo 'Triggering silenced error' . PHP_EOL;
@$a['missing'];
echo 'End'
?>
--EXPECTF--
Triggering silenced error
End
