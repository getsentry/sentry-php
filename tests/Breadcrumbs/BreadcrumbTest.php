<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Breadcrumbs;

use Raven\Breadcrumbs\Breadcrumb;
use Raven\Client;

/**
 * @group time-sensitive
 */
class BreadcrumbTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Raven\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the $level argument must be one of the Raven\Client::LEVEL_* constants.
     */
    public function testConstructorThrowsOnInvalidLevel()
    {
        new Breadcrumb('foo', 'bar', 'baz');
    }

    /**
     * @expectedException \Raven\Exception\InvalidArgumentException
     * @expectedExceptionMessage The value of the $level argument must be one of the Raven\Client::LEVEL_* constants.
     */
    public function testSetLevelThrowsOnInvalidLevel()
    {
        $breadcrumb = new Breadcrumb(Client::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb->setLevel('bar');
    }

    public function testConstructor()
    {
        $breadcrumb = new Breadcrumb(Client::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    public function testCreate()
    {
        $breadcrumb = Breadcrumb::create(Client::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', 'foo bar', ['baz']);

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($property, $expectedValue)
    {
        $breadcrumb = new Breadcrumb(Client::LEVEL_INFO, Breadcrumb::TYPE_ERROR, 'foo', 'foo bar', ['baz']);

        call_user_func([$breadcrumb, 'set' . ucfirst($property)], $expectedValue);

        $this->assertEquals($expectedValue, call_user_func([$breadcrumb, 'get' . ucfirst($property)]));
    }

    public function gettersAndSettersDataProvider()
    {
        return [
            ['category', 'foo'],
            ['level', Client::LEVEL_WARNING],
            ['type', Breadcrumb::TYPE_USER],
            ['message', 'foo bar'],
            ['metadata', ['foo', 'bar']],
            ['timestamp', 123],
        ];
    }
}
