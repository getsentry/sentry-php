<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\EventHint;
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
            'extra' => ['foo' => 'bar'],
        ]);

        $this->assertSame($exception, $hint->exception);
        $this->assertSame($stacktrace, $hint->stacktrace);
        $this->assertSame(['foo' => 'bar'], $hint->extra);
    }

    /**
     * @dataProvider createFromArrayWithInvalidValuesDataProvider
     */
    public function testCreateFromArrayWithInvalidValues(array $hintData, string $expectedExceptionMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        EventHint::fromArray($hintData);
    }

    public function createFromArrayWithInvalidValuesDataProvider(): \Generator
    {
        yield [
            ['exception' => 'foo'],
            'The value of the "exception" field must be an instance of a class implementing the "Throwable" interface. Got: "string".',
        ];

        yield [
            ['stacktrace' => 'foo'],
            'The value of the "stacktrace" field must be an instance of the "Sentry\\Stacktrace" class. Got: "string".',
        ];

        yield [
            ['extra' => 'foo'],
            'The value of the "extra" field must be an array. Got: "string".',
        ];
    }
}
