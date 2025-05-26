<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Tracing\SpanId;

final class SpanIdTest extends TestCase
{
    public function testConstructor(): void
    {
        $value = '566e3688a61d4bc8';

        $this->assertSame($value, (string) new SpanId($value));
    }

    /**
     * @dataProvider constructorThrowsOnInvalidValueDataProvider
     */
    public function testConstructorThrowsOnInvalidValue(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $value argument must be a 16 characters long hexadecimal string.');

        new SpanId($value);
    }

    public static function constructorThrowsOnInvalidValueDataProvider(): \Generator
    {
        yield 'Value too long' => ['566e3688a61d4bc88'];
        yield 'Value too short' => ['566e3688a61d4b8'];
        yield 'Value with invalid characters' => ['88951642d6f14a1g'];
    }
}
