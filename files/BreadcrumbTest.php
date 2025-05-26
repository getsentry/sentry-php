<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Symfony\Bridge\PhpUnit\ClockMock;

final class BreadcrumbTest extends TestCase
{
    public function testConstructorThrowsOnInvalidLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.');

        new Breadcrumb('foo', 'bar', 'baz');
    }

    public function testWithLevelThrowsOnInvalidLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.');

        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb->withLevel('bar');
    }

    /**
     * @dataProvider constructorDataProvider
     */
    public function testConstructor(array $constructorArgs, string $expectedLevel, string $expectedType, string $expectedCategory, ?string $expectedMessage, array $expectedMetadata, float $expectedTimestamp): void
    {
        ClockMock::withClockMock(1615588578.6652);

        $breadcrumb = new Breadcrumb(...$constructorArgs);

        $this->assertSame($expectedCategory, $breadcrumb->getCategory());
        $this->assertSame($expectedLevel, $breadcrumb->getLevel());
        $this->assertSame($expectedMessage, $breadcrumb->getMessage());
        $this->assertSame($expectedType, $breadcrumb->getType());
        $this->assertSame($expectedMetadata, $breadcrumb->getMetadata());
        $this->assertSame($expectedTimestamp, $breadcrumb->getTimestamp());
    }

    public static function constructorDataProvider(): \Generator
    {
        yield [
            [
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_USER,
                'log',
            ],
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_USER,
            'log',
            null,
            [],
            1615588578.6652,
        ];

        yield [
            [
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_USER,
                'log',
                'something happened',
                ['foo' => 'bar'],
                null,
            ],
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_USER,
            'log',
            'something happened',
            ['foo' => 'bar'],
            1615588578.6652,
        ];

        yield [
            [
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_USER,
                'log',
                null,
                [],
                1615590096.3244,
            ],
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_USER,
            'log',
            null,
            [],
            1615590096.3244,
        ];
    }

    /**
     * @dataProvider fromArrayDataProvider
     */
    public function testFromArray(array $requestData, string $expectedLevel, string $expectedType, string $expectedCategory, ?string $expectedMessage, array $expectedMetadata, float $expectedTimestamp): void
    {
        ClockMock::withClockMock(1615588578.6652);

        $breadcrumb = Breadcrumb::fromArray($requestData);

        $this->assertSame($expectedLevel, $breadcrumb->getLevel());
        $this->assertSame($expectedType, $breadcrumb->getType());
        $this->assertSame($expectedCategory, $breadcrumb->getCategory());
        $this->assertSame($expectedMessage, $breadcrumb->getMessage());
        $this->assertSame($expectedMetadata, $breadcrumb->getMetadata());
        $this->assertSame($expectedTimestamp, $breadcrumb->getTimestamp());
    }

    public static function fromArrayDataProvider(): iterable
    {
        yield [
            [
                'level' => Breadcrumb::LEVEL_INFO,
                'type' => Breadcrumb::TYPE_USER,
                'category' => 'foo',
                'message' => 'foo bar',
                'data' => ['baz'],
                'timestamp' => 1615590096.3244,
            ],
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_USER,
            'foo',
            'foo bar',
            ['baz'],
            1615590096.3244,
        ];

        yield [
            [
                'level' => Breadcrumb::LEVEL_INFO,
                'category' => 'foo',
            ],
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'foo',
            null,
            [],
            1615588578.6652,
        ];
    }

    public function testWithCategory(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withCategory('bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame('bar', $newBreadcrumb->getCategory());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withCategory('bar'));
    }

    public function testWithLevel(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withLevel(Breadcrumb::LEVEL_WARNING);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame(Breadcrumb::LEVEL_WARNING, $newBreadcrumb->getLevel());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withLevel(Breadcrumb::LEVEL_WARNING));
    }

    public function testWithType(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withType(Breadcrumb::TYPE_ERROR);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame(Breadcrumb::TYPE_ERROR, $newBreadcrumb->getType());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withType(Breadcrumb::TYPE_ERROR));
    }

    public function testWithMessage(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMessage('foo bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame('foo bar', $newBreadcrumb->getMessage());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withMessage('foo bar'));
    }

    public function testWithMetadata(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMetadata('foo', 'bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertNotContains('foo', $breadcrumb->getMetadata());
        $this->assertSame(['foo' => 'bar'], $newBreadcrumb->getMetadata());
    }

    public function testWithoutMetadata(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', null, ['foo' => 'bar']);
        $newBreadcrumb = $breadcrumb->withoutMetadata('foo');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame(['foo' => 'bar'], $breadcrumb->getMetadata());
        $this->assertArrayNotHasKey('foo', $newBreadcrumb->getMetadata());
    }

    public function testWithTimestamp(): void
    {
        $timestamp = 12345.678;
        $newTimestamp = 987.654;
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', null, ['foo' => 'bar'], $timestamp);
        $newBreadcrumb = $breadcrumb->withTimestamp($timestamp);

        $this->assertSame($breadcrumb, $newBreadcrumb);

        $newBreadcrumb = $breadcrumb->withTimestamp($newTimestamp);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame($timestamp, $breadcrumb->getTimestamp());
        $this->assertSame($newTimestamp, $newBreadcrumb->getTimestamp());
    }
}
