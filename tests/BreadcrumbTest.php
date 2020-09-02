<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;

/**
 * @group time-sensitive
 */
final class BreadcrumbTest extends TestCase
{
    /**
     * @expectedException \Sentry\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.
     */
    public function testConstructorThrowsOnInvalidLevel(): void
    {
        new Breadcrumb('foo', 'bar', 'baz');
    }

    /**
     * @expectedException \Sentry\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.
     */
    public function testSetLevelThrowsOnInvalidLevel(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb->withLevel('bar');
    }

    public function testConstructor(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertSame('foo', $breadcrumb->getCategory());
        $this->assertSame(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertSame('foo bar', $breadcrumb->getMessage());
        $this->assertSame(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertSame(['baz'], $breadcrumb->getMetadata());
        $this->assertSame(microtime(true), $breadcrumb->getTimestamp());
    }

    /**
     * @dataProvider fromArrayDataProvider
     */
    public function testFromArray(array $requestData, string $expectedLevel, string $expectedType, string $expectedCategory, ?string $expectedMessage, array $expectedMetadata): void
    {
        $breadcrumb = Breadcrumb::fromArray($requestData);

        $this->assertSame($expectedLevel, $breadcrumb->getLevel());
        $this->assertSame($expectedType, $breadcrumb->getType());
        $this->assertSame($expectedCategory, $breadcrumb->getCategory());
        $this->assertSame($expectedMessage, $breadcrumb->getMessage());
        $this->assertSame($expectedMetadata, $breadcrumb->getMetadata());
    }

    public function fromArrayDataProvider(): iterable
    {
        yield [
            [
                'level' => Breadcrumb::LEVEL_INFO,
                'type' => Breadcrumb::TYPE_USER,
                'category' => 'foo',
                'message' => 'foo bar',
                'data' => ['baz'],
            ],
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_USER,
            'foo',
            'foo bar',
            ['baz'],
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
}
