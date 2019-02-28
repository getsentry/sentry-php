--TEST--
Test that the error handler ignores silenced errors
--FILE--
<?php

namespace Sentry\Tests;

use Sentry\ErrorHandler;
use Sentry\Tests\Fixtures\classes\StubErrorListener;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

ErrorHandler::addErrorListener(new StubErrorListener(function () {
    echo 'Callback invoked' . PHP_EOL;
}));

echo 'Triggering silenced error' . PHP_EOL;
@$a['missing'];
echo 'End'
?>
--EXPECTF--
Triggering silenced error
End
