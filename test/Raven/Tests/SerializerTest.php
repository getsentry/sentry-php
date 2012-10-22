<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_SerializerTestObject
{
    private $foo = 'bar';
}

class Raven_Tests_SerializerTest extends PHPUnit_Framework_TestCase
{
    public function testArraysAreArrays()
    {
        $input = array(1, 2, 3);
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals($result, array('1', '2', '3'));
    }

    public function testObjectsAreStrings()
    {
        $input = new Raven_StacktraceTestObject();
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals($result, 'Object Raven_StacktraceTestObject');
    }

    public function testIntsAreInts()
    {
        $input = 1;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_integer($result));
        $this->assertEquals($result, 1);
    }

    public function testRecursionMaxDepth()
    {
        $input = array();
        $input[] = &$input;
        $result = Raven_Serializer::serialize($input, 3);
        $this->assertEquals($result, array(array(array('Array of length 1'))));
    }

}
