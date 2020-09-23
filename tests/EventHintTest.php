<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\EventHint;
use Sentry\Exception\InvalidArgumentException;
use Sentry\Frame;
use Sentry\Stacktrace;

final class EventHintTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $exception = new \Exception();
        $stacktrace = new Stacktrace([
            new Frame('function_1', 'path/to/file_1', 10),
        ]);

        $hint = EventHint::fromArray([
            'exception' => $exception,
            'stacktrace' => $stacktrace,
        ]);

        $this->assertEquals($exception, $hint->exception);
        $this->assertEquals($stacktrace, $hint->stacktrace);
    }

    public function testThrowsExceptionOnInvalidKeyInArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no EventHint attribute called "missing_property".');

        EventHint::fromArray([
            'missing_property' => 'some value',
        ]);
    }

    public function testThrowsExceptionOnInvalidKeyInArrayWhenValidKeyIsPresent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no EventHint attribute called "missing_property".');

        EventHint::fromArray([
            'exception' => new \Exception(),
            'missing_property' => 'some value',
        ]);
    }
}
