<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\EventId;

final class EventIdTest extends TestCase
{
    public function testConstructor(): void
    {
        $value = '566e3688a61d4bc888951642d6f14a19';

        $this->assertSame($value, (string) new EventId($value));
    }

    /**
     * @dataProvider constructorThrowsOnInvalidValueDataProvider
     */
    public function testConstructorThrowsOnInvalidValue(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The $value argument must be a 32 characters long hexadecimal string.');

        new EventId($value);
    }

    public static function constructorThrowsOnInvalidValueDataProvider(): \Generator
    {
        yield 'Value too long' => ['566e3688a61d4bc888951642d6f14a199'];
        yield 'Value too short' => ['566e3688a61d4bc888951642d6f14a1'];
        yield 'Value with invalid characters' => ['566e3688a61d4bc888951642d6f14a1g'];
    }
}
