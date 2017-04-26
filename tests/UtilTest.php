<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

class StacktraceTestObject
{
    private $foo = 'bar';
}

class Raven_Tests_UtilTest extends \PHPUnit_Framework_TestCase
{
    public function testGetReturnsDefaultOnMissing()
    {
        $input = array('foo' => 'bar');
        $result = \Raven\Util::get($input, 'baz', 'foo');
        $this->assertEquals('foo', $result);
    }

    public function testGetReturnsPresentValuesEvenWhenEmpty()
    {
        $input = array('foo' => '');
        $result = \Raven\Util::get($input, 'foo', 'bar');
        $this->assertEquals('', $result);
    }
}
