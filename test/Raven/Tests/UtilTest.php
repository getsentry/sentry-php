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
    public function testToStringSanitizesArrays()
    {
        $input = array(1, 2, 3);
        $result = Raven_Util::makeJsonCompatible($input);
        $this->assertEquals($result, array('1', '2', '3'));
    }

    public function testToStringSanitizesObjectsToStrings()
    {
        $input = new Raven_StacktraceTestObject();
        $result = Raven_Util::makeJsonCompatible($input);
        $this->assertEquals($result, '<Raven_StacktraceTestObject>');
    }
}
