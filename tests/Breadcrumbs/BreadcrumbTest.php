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
    public function testConstructorThrowsOnWrongLevel()
    {
        new Breadcrumb('foo', 'bar', 'baz');
    }

    public function testGetters()
    {
        $breadcrumb = new Breadcrumb(Client::LEVEL_INFO, Breadcrumb::TYPE_USER, 'foo', 'foo bar', [
            'bar' => 'baz',
        ]);

        $breadcrumb->setMessage('foo bar');

        $this->assertEquals('foo', $breadcrumb->getCategory());
        $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
        $this->assertEquals('foo bar', $breadcrumb->getMessage());
        $this->assertEquals(Breadcrumb::TYPE_USER, $breadcrumb->getType());
        $this->assertEquals(['bar' => 'baz'], $breadcrumb->getMetadata());
        $this->assertEquals(microtime(true), $breadcrumb->getTimestamp());
    }
}
