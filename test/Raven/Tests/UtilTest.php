<?php

use Raven\Util;

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_StacktraceTestObject
{
    private $foo = 'bar';
}

class Raven_Tests_UtilTest extends PHPUnit_Framework_TestCase
{
    public function testGetReturnsDefaultOnMissing()
    {
        $input = array('foo' => 'bar');
        $result = Util::get($input, 'baz', 'foo');
        $this->assertEquals($result, 'foo');
    }

    public function testGetReturnsPresentValuesEvenWhenEmpty()
    {
        $input = array('foo' => '');
        $result = Util::get($input, 'foo', 'bar');
        $this->assertEquals($result, '');
    }
}
