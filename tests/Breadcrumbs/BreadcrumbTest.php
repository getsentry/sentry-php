<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Breadcrumbs;

use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;

/**
 * @group time-sensitive
 */
class BreadcrumbTest extends TestCase
{
    /**
     * @expectedException \Sentry\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.
     */
    public function testConstructorThrowsOnInvalidLevel()
    {
        new Breadcrumb('foo', 'bar', 'baz');
    }

    /**
     * @expectedException \Sentry\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the $level argument must be one of the Breadcrumb::LEVEL_* constants.
     */
    public function testSetLevelThrowsOnInvalidLevel()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb->withLevel('bar');
    }

    public function testConstructor()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    public function testCreate()
    {
        $breadcrumb = Breadcrumb::create(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    public function testWithCategory()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withCategory('bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals('bar', $newBreadcrumb->getCategory());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withCategory('bar'));
    }

    public function testWithLevel()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withLevel(Breadcrumb::LEVEL_WARNING);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(Breadcrumb::LEVEL_WARNING, $newBreadcrumb->getLevel());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withLevel(Breadcrumb::LEVEL_WARNING));
    }

    public function testWithType()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withType(Breadcrumb::TYPE_ERROR);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(Breadcrumb::TYPE_ERROR, $newBreadcrumb->getType());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withType(Breadcrumb::TYPE_ERROR));
    }

    public function testWithMessage()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMessage('foo bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals('foo bar', $newBreadcrumb->getMessage());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withMessage('foo bar'));
    }

    public function testWithTimestamp()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withTimestamp(123);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(123, $newBreadcrumb->getTimestamp());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withTimestamp(123));
    }

    public function testWithMetadata()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMetadata('foo', 'bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertNotContains('foo', $breadcrumb->getMetadata());
        $this->assertSame(['foo' => 'bar'], $newBreadcrumb->getMetadata());
    }

    public function testWithoutMetadata()
    {
        $breadcrumb = new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', null, ['foo' => 'bar']);
        $newBreadcrumb = $breadcrumb->withoutMetadata('foo');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame(['foo' => 'bar'], $breadcrumb->getMetadata());
        $this->assertArrayNotHasKey('foo', $newBreadcrumb->getMetadata());
    }
}
