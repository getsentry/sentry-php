<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\Stacktrace;

final class ExceptionDataBagTest extends TestCase
{
    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(array $constructorArgs, string $expectedType, string $expectedValue, ?Stacktrace $expectedStackTrace, ?ExceptionMechanism $expectedExceptionMechansim)
    {
        $exceptionDataBag = new ExceptionDataBag(...$constructorArgs);

        $this->assertSame($expectedType, $exceptionDataBag->getType());
        $this->assertSame($expectedValue, $exceptionDataBag->getValue());
        $this->assertSame($expectedStackTrace, $exceptionDataBag->getStacktrace());
        $this->assertSame($expectedExceptionMechansim, $exceptionDataBag->getMechanism());
    }

    public static function constructorDataProvider(): \Generator
    {
        yield [
            [
                new \RuntimeException('foo bar'),
                null,
                null,
            ],
            \RuntimeException::class,
            'foo bar',
            null,
            null,
        ];

        $strackTarce = new Stacktrace([
            new Frame('test_function', '/path/to/file', 10, null, '/path/to/file'),
        ]);
        $exceptionMechansim = new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, false);

        yield [
            [
                new \RuntimeException('foo bar'),
                $strackTarce,
                $exceptionMechansim,
            ],
            \RuntimeException::class,
            'foo bar',
            $strackTarce,
            $exceptionMechansim,
        ];
    }

    public function testSetType(): void
    {
        $exceptionDataBag = new ExceptionDataBag(new \RuntimeException());

        $exceptionDataBag->setType('foo bar');

        $this->assertSame('foo bar', $exceptionDataBag->getType());
    }

    public function testSetValue(): void
    {
        $exceptionDataBag = new ExceptionDataBag(new \RuntimeException());

        $exceptionDataBag->setValue('foo bar');

        $this->assertSame('foo bar', $exceptionDataBag->getValue());
    }

    public function testSetStacktrace(): void
    {
        $exceptionDataBag = new ExceptionDataBag(new \RuntimeException());

        $stacktrace = new Stacktrace([
            new Frame('test_function', '/path/to/file', 10, null, '/path/to/file'),
        ]);

        $exceptionDataBag->setStacktrace($stacktrace);

        $this->assertSame($stacktrace, $exceptionDataBag->getStacktrace());
    }

    public function testSetMechanism(): void
    {
        $exceptionDataBag = new ExceptionDataBag(new \RuntimeException());
        $exceptionMechanism = new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, false);

        $exceptionDataBag->setMechanism($exceptionMechanism);

        $this->assertSame($exceptionMechanism, $exceptionDataBag->getMechanism());
    }
}
