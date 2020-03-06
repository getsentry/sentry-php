--TEST--
Test that the error listener gets registered using the deprecated method
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ErrorHandler;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = \dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

ErrorHandler::addErrorListener(static function (): void {
    echo 'Error listener called';
});

$foo++;
--EXPECTF--
Error listener called
Notice: Undefined variable: foo in %s on line %d
