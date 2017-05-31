<?php

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
        $result = Raven_Util::get($input, 'baz', 'foo');
        $this->assertEquals('foo', $result);
    }

    public function testGetReturnsPresentValuesEvenWhenEmpty()
    {
        $input = array('foo' => '');
        $result = Raven_Util::get($input, 'foo', 'bar');
        $this->assertEquals('', $result);
    }

    public function testGetCallableParamNumWorks()
    {
        $fn_one = function () {
            return true;
        };

        $fn_two = function ($a, $b) {
            return true;
        };

        $fn_three = function ($a, $b, $c, $d) {
            return true;
        };

        $results = array(
            Raven_Util::getCallableParamNum($fn_one),
            Raven_Util::getCallableParamNum($fn_two),
            Raven_Util::getCallableParamNum($fn_three)
        );

        $this->assertEquals(array(0, 2, 4), $results);
    }
}
