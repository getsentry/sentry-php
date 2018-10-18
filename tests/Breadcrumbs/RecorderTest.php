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
use Sentry\Breadcrumbs\Recorder;
use Sentry\Severity;

/**
 * @group time-sensitive
 */
class RecorderTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $maxSize argument must be an integer greater than 0.
     *
     * @dataProvider constructorMaxSizeDataProvider
     */
    public function testConstructorThrowsExceptionOnInvalidMaxSize($maxSize)
    {
        new Recorder($maxSize);
    }

    public function constructorMaxSizeDataProvider()
    {
        return [
            [0],
            ['foo'],
        ];
    }

    public function testRecord()
    {
        $breadcrumb = new Breadcrumb(Severity::debug(), Breadcrumb::TYPE_USER, 'foo');
        $breadcrumb2 = new Breadcrumb(Severity::error(), Breadcrumb::TYPE_NAVIGATION, 'bar');

        $recorder = new Recorder(3);

        $this->assertCount(0, $recorder);
        $this->assertEquals([], iterator_to_array($recorder));

        $recorder->record($breadcrumb);

        $this->assertCount(1, $recorder);
        $this->assertEquals([$breadcrumb], iterator_to_array($recorder));

        for ($i = 0; $i < 2; ++$i) {
            $recorder->record($breadcrumb);
        }

        $this->assertCount(3, $recorder);
        $this->assertEquals([$breadcrumb, $breadcrumb, $breadcrumb], iterator_to_array($recorder));

        for ($i = 0; $i < 2; ++$i) {
            $recorder->record($breadcrumb2);
        }

        $this->assertCount(3, $recorder);
        $this->assertEquals([$breadcrumb, $breadcrumb2, $breadcrumb2], iterator_to_array($recorder));
    }

    public function testClear()
    {
        $recorder = new Recorder(1);
        $breadcrumb = new Breadcrumb(Severity::debug(), Breadcrumb::TYPE_USER, 'foo');

        $this->assertCount(0, $recorder);
        $this->assertEquals([], iterator_to_array($recorder));

        $recorder->record($breadcrumb);

        $this->assertCount(1, $recorder);
        $this->assertEquals([$breadcrumb], iterator_to_array($recorder));

        $recorder->clear();

        $this->assertCount(0, $recorder);
        $this->assertEquals([], iterator_to_array($recorder));
    }
}
