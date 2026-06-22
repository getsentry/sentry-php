--TEST--
Test that resetting the fatal error handler state re-arms OOM handling
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

error_reporting(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);

function getErrorHandlerProperty(string $name): \ReflectionProperty
{
    $property = new \ReflectionProperty(ErrorHandler::class, $name);
    $property->setAccessible(true);

    return $property;
}

function setErrorHandlerStaticProperty(string $name, $value): void
{
    getErrorHandlerProperty($name)->setValue(null, $value);
}

function getErrorHandlerStaticProperty(string $name)
{
    return getErrorHandlerProperty($name)->getValue();
}

ErrorHandler::registerOnceFatalErrorHandler(1234);

setErrorHandlerStaticProperty('disableFatalErrorHandler', true);
setErrorHandlerStaticProperty('didIncreaseMemoryLimit', true);
setErrorHandlerStaticProperty('reservedMemory', null);

ErrorHandler::resetFatalErrorHandlerState();

var_dump(getErrorHandlerStaticProperty('disableFatalErrorHandler'));
var_dump(getErrorHandlerStaticProperty('didIncreaseMemoryLimit'));
var_dump(\strlen(getErrorHandlerStaticProperty('reservedMemory')));

?>
--EXPECT--
bool(false)
bool(false)
int(1234)
