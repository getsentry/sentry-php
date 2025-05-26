<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Severity;

final class SeverityTest extends TestCase
{
    public function testConstructorThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foo" is not a valid enum value.');

        new Severity('foo');
    }

    /**
     * @dataProvider constantsDataProvider
     */
    public function testConstructor(Severity $severity, string $expectedStringRepresentation): void
    {
        $this->assertTrue($severity->isEqualTo(new Severity($expectedStringRepresentation)));
    }

    /**
     * @dataProvider constantsDataProvider
     */
    public function testToString(Severity $severity, string $expectedStringRepresentation): void
    {
        $this->assertSame($expectedStringRepresentation, (string) $severity);
    }

    public static function constantsDataProvider(): array
    {
        return [
            [Severity::debug(), 'debug'],
            [Severity::info(), 'info'],
            [Severity::warning(), 'warning'],
            [Severity::error(), 'error'],
            [Severity::fatal(), 'fatal'],
        ];
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
     * @dataProvider levelsDataProvider
     */
    public function testFromError(int $errorLevel, string $expectedSeverity): void
    {
        $this->assertSame($expectedSeverity, (string) Severity::fromError($errorLevel));
    }

    public static function levelsDataProvider(): array
    {
        return [
            // Warning
            [\E_DEPRECATED, 'warning'],
            [\E_USER_DEPRECATED, 'warning'],
            [\E_WARNING, 'warning'],
            [\E_USER_WARNING, 'warning'],
            // Fatal
            [\E_ERROR, 'fatal'],
            [\E_PARSE, 'fatal'],
            [\E_CORE_ERROR, 'fatal'],
            [\E_CORE_WARNING, 'fatal'],
            [\E_COMPILE_ERROR, 'fatal'],
            [\E_COMPILE_WARNING, 'fatal'],
            // Error
            [\E_RECOVERABLE_ERROR, 'error'],
            [\E_USER_ERROR, 'error'],
            // Info
            [\E_NOTICE, 'info'],
            [\E_USER_NOTICE, 'info'],
            // This is \E_STRICT which has been deprecated in PHP 8.4 so we should not reference it directly to prevent deprecation notices
            [2048, 'info'],
        ];
    }
}
