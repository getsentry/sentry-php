<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Severity;

final class SeverityTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "foo" is not a valid enum value.
     */
    public function testConstructorThrowsOnInvalidValue(): void
    {
        new Severity('foo');
    }

    public function testDebug(): void
    {
        $severity = Severity::debug();

        $this->assertSame(Severity::DEBUG, (string) $severity);
    }

    public function testInfo(): void
    {
        $severity = Severity::info();

        $this->assertSame(Severity::INFO, (string) $severity);
    }

    public function testWarning(): void
    {
        $severity = Severity::warning();

        $this->assertSame(Severity::WARNING, (string) $severity);
    }

    public function testError(): void
    {
        $severity = Severity::error();

        $this->assertSame(Severity::ERROR, (string) $severity);
    }

    public function testFatal(): void
    {
        $severity = Severity::fatal();

        $this->assertSame(Severity::FATAL, (string) $severity);
    }

    public function testIsEqualTo(): void
    {
        $severity1 = Severity::error();
        $severity2 = Severity::error();
        $severity3 = Severity::fatal();

        $this->assertTrue($severity1->isEqualTo($severity2));
        $this->assertFalse($severity1->isEqualTo($severity3));
    }

    /**
     * @dataProvider fromErrorDataProvider
     */
    public function testFromError(int $errorLevel, string $expectedSeverity): void
    {
        $this->assertSame($expectedSeverity, Severity::fromError($errorLevel)->__toString());
    }

    public function fromErrorDataProvider(): array
    {
        return [
            // Warning
            [E_DEPRECATED, Severity::WARNING],
            [E_USER_DEPRECATED, Severity::WARNING],
            [E_WARNING, Severity::WARNING],
            [E_USER_WARNING, Severity::WARNING],
            // Fatal
            [E_ERROR, Severity::FATAL],
            [E_PARSE, Severity::FATAL],
            [E_CORE_ERROR, Severity::FATAL],
            [E_CORE_WARNING, Severity::FATAL],
            [E_COMPILE_ERROR, Severity::FATAL],
            [E_COMPILE_WARNING, Severity::FATAL],
            // Error
            [E_RECOVERABLE_ERROR, Severity::ERROR],
            [E_USER_ERROR, Severity::ERROR],
            // Info
            [E_NOTICE, Severity::INFO],
            [E_USER_NOTICE, Severity::INFO],
            [E_STRICT, Severity::INFO],
        ];
    }
}
