--TEST--
Test that the fatal error handler truncates a deep fatal error backtrace to the 50 outermost and 200 innermost stack frames
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip - only works for PHP 8.5 and above');
}
--INI--
fatal_error_backtraces=1
--FILE--
<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\ErrorHandler;
use Sentry\Exception\FatalErrorException;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\StacktraceBuilder;

$vendor = __DIR__;

while (!file_exists($vendor . '/vendor')) {
    $vendor = dirname($vendor);
}

require $vendor . '/vendor/autoload.php';

$errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
$errorHandler->addFatalErrorHandlerListener(static function (FatalErrorException $exception): void {
    $options = new Options();
    $stacktraceBuilder = new StacktraceBuilder($options, new RepresentationSerializer($options));

    $frames = $stacktraceBuilder->buildFromException($exception)->getFrames();
    $allFrames = $stacktraceBuilder->buildFromBacktrace(error_get_last()['trace'], $exception->getFile(), $exception->getLine())->getFrames();

    echo 'All frames: ' . \count($allFrames) . PHP_EOL;
    echo 'Frames: ' . \count($frames) . PHP_EOL;

    // Frames are ordered from the outermost to the innermost one
    var_dump($frames == array_merge(\array_slice($allFrames, 0, 50), \array_slice($allFrames, -200)));
});

function outerRecursion(int $depth): void
{
    if ($depth < 100) {
        outerRecursion($depth + 1);

        return;
    }

    innerRecursion(0);
}

function innerRecursion(int $depth): void
{
    if ($depth < 250) {
        innerRecursion($depth + 1);

        return;
    }

    final class TestClass implements \JsonSerializable
    {
    }
}

outerRecursion(0);
?>
--EXPECTF--
Fatal error: Class Sentry\Tests\TestClass contains 1 abstract method and must therefore be declared abstract or implement the remaining method (JsonSerializable::jsonSerialize) in %s on line %d
Stack trace:
%A
All frames: %d
Frames: 250
bool(true)
