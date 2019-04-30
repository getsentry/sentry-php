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

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    /**
     * @dataProvider fromArrayDataProvider
     */
    public function testFromArray(array $requestData, array $expectedResult): void
    {
        $expectedResult['timestamp'] = microtime(true);
        $breadcrumb = Breadcrumb::fromArray($requestData);

        $this->assertEquals($expectedResult, $breadcrumb->toArray());
    }

    public function fromArrayDataProvider(): array
    {
        return [
            [
                [
                    'level' => Breadcrumb::LEVEL_INFO,
                    'type' => Breadcrumb::TYPE_USER,
                    'category' => 'foo',
                    'message' => 'foo bar',
                    'data' => ['baz'],
                ],
                [
                    'level' => Breadcrumb::LEVEL_INFO,
                    'type' => Breadcrumb::TYPE_USER,
                    'category' => 'foo',
                    'message' => 'foo bar',
                    'data' => ['baz'],
                ],
            ],
            [
                [
                    'level' => Breadcrumb::LEVEL_INFO,
                    'category' => 'foo',
                ],
                [
                    'level' => Breadcrumb::LEVEL_INFO,
                    'type' => Breadcrumb::TYPE_DEFAULT,
                    'category' => 'foo',
                    'message' => null,
                    'data' => [],
                ],
            ],
        ];
    }

    public function testWithCategory(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withCategory('bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals('bar', $newBreadcrumb->getCategory());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withCategory('bar'));
    }

    public function testWithLevel(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withLevel(Breadcrumb::LEVEL_WARNING);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(Breadcrumb::LEVEL_WARNING, $newBreadcrumb->getLevel());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withLevel(Breadcrumb::LEVEL_WARNING));
    }

    public function testWithType(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withType(Breadcrumb::TYPE_ERROR);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(Breadcrumb::TYPE_ERROR, $newBreadcrumb->getType());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withType(Breadcrumb::TYPE_ERROR));
    }

    public function testWithMessage(): void
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMessage('foo bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals('foo bar', $newBreadcrumb->getMessage());
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

    public function testJsonSerialize(): void
    {
        $type = Breadcrumb::TYPE_USER;
        $level = Breadcrumb::LEVEL_INFO;
        $category = 'foo';
        $data = ['baz' => 'bar'];
        $message = 'message';
        $breadcrumb = new Breadcrumb($level, $type, $category, $message, $data);

        $expected = [
            'type' => $type,
            'category' => $category,
            'message' => $message,
            'level' => $level,
            'timestamp' => microtime(true),
            'data' => $data,
        ];

        $this->assertEquals($expected, $breadcrumb->jsonSerialize());
    }
}
