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
use Sentry\Client;
use Sentry\Severity;

/**
 * @group time-sensitive
 */
class BreadcrumbTest extends TestCase
{
    public function testConstructor()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    public function testCreate()
    {
        $breadcrumb = Breadcrumb::create(Severity::info(), Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    public function testWithCategory()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withCategory('bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals('bar', $newBreadcrumb->getCategory());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withCategory('bar'));
    }

    public function testWithLevel()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withLevel(Severity::warning());

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertTrue($newBreadcrumb->getLevel()->isEqualTo(Severity::warning()));
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withLevel(Severity::warning()));
    }

    public function testWithType()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withType(Breadcrumb::TYPE_ERROR);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(Breadcrumb::TYPE_ERROR, $newBreadcrumb->getType());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withType(Breadcrumb::TYPE_ERROR));
    }

    public function testWithMessage()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMessage('foo bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals('foo bar', $newBreadcrumb->getMessage());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withMessage('foo bar'));
    }

    public function testWithTimestamp()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withTimestamp(123);

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertEquals(123, $newBreadcrumb->getTimestamp());
        $this->assertSame($newBreadcrumb, $newBreadcrumb->withTimestamp(123));
    }

    public function testWithMetadata()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo');
        $newBreadcrumb = $breadcrumb->withMetadata('foo', 'bar');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertNotContains('foo', $breadcrumb->getMetadata());
        $this->assertSame(['foo' => 'bar'], $newBreadcrumb->getMetadata());
    }

    public function testWithoutMetadata()
    {
        $breadcrumb = new Breadcrumb(Severity::info(), Breadcrumb::TYPE_USER, 'foo', null, ['foo' => 'bar']);
        $newBreadcrumb = $breadcrumb->withoutMetadata('foo');

        $this->assertNotSame($breadcrumb, $newBreadcrumb);
        $this->assertSame(['foo' => 'bar'], $breadcrumb->getMetadata());
        $this->assertArrayNotHasKey('foo', $newBreadcrumb->getMetadata());
    }
}
