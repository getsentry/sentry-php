<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Severity;

final class SeverityTest extends TestCase
{
    /**
     * @dataProvider fromStringDataProvider
     */
    public function testFromString(string $value, bool $isAllowed): void
    {
        if (!$isAllowed) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf('The "%s" is not a valid enum value.', $value));
        }

        $severity = Severity::fromString($value);

        $this->assertSame($value, (string) $severity);
    }

    public function fromStringDataProvider(): array
    {
        return [
            [
                Severity::SEVERITY_DEBUG,
                true,
            ],
            [
                Severity::SEVERITY_INFO,
                true,
            ],
            [
                Severity::SEVERITY_WARNING,
                true,
            ],
            [
                Severity::SEVERITY_ERROR,
                true,
            ],
            [
                Severity::SEVERITY_FATAL,
                true,
            ],
            [
                'foo',
                false,
            ],
        ];
    }

    public function testDebug(): void
    {
        $severity = Severity::debug();

        $this->assertSame(Severity::SEVERITY_DEBUG, (string) $severity);
    }

    public function testInfo(): void
    {
        $severity = Severity::info();

        $this->assertSame(Severity::SEVERITY_INFO, (string) $severity);
    }

    public function testWarning(): void
    {
        $severity = Severity::warning();

        $this->assertSame(Severity::SEVERITY_WARNING, (string) $severity);
    }

    public function testError(): void
    {
        $severity = Severity::error();

        $this->assertSame(Severity::SEVERITY_ERROR, (string) $severity);
    }

    public function testFatal(): void
    {
        $severity = Severity::fatal();

        $this->assertSame(Severity::SEVERITY_FATAL, (string) $severity);
    }

    public function testIsEqualTo(): void
    {
        $severity1 = Severity::error();
        $severity2 = Severity::error();
        $severity3 = Severity::fatal();

        $this->assertTrue($severity1->isEqualTo($severity2));
        $this->assertFalse($severity1->isEqualTo($severity3));
    }
}
