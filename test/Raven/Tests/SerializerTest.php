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
        $this->assertEquals(array('1', '2', '3'), $result);
    }

    public function testObjectsAreStrings()
    {
        $input = new Raven_StacktraceTestObject();
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals('Object Raven_StacktraceTestObject', $result);
    }

    public function testIntsAreInts()
    {
        $input = 1;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_integer($result));
        $this->assertEquals(1, $result);
    }

    public function testFloats()
    {
        $input = 1.5;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_float($result));
        $this->assertEquals(1.5, $result);
    }

    public function testBooleans()
    {
        $input = true;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_bool($result));
        $this->assertEquals(true, $result);

        $input = false;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_bool($result));
        $this->assertEquals(false, $result);
    }

    public function testNull()
    {
        $input = null;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_null($result));
        $this->assertEquals(null, $result);
    }

    public function testRecursionMaxDepth()
    {
        $input = array();
        $input[] = &$input;
        $result = Raven_Serializer::serialize($input, 3);
        $this->assertEquals(array(array(array('Array of length 1'))), $result);
    }
}

class Raven_Tests_ReprSerializerTest extends PHPUnit_Framework_TestCase
{
    public function testArraysAreArrays()
    {
        $input = array(1, 2, 3);
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals(array('1', '2', '3'), $result);
    }

    public function testObjectsAreStrings()
    {
        $input = new Raven_StacktraceTestObject();
        $result = Raven_Serializer::serialize($input);
        $this->assertEquals('Object Raven_StacktraceTestObject', $result);
    }

    public function testIntsAreInts()
    {
        $input = 1;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_integer($result));
        $this->assertEquals(1, $result);
    }

    public function testFloats()
    {
        $input = 1.5;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_string($result));
        $this->assertEquals('1.5', $result);
    }

    public function testBooleans()
    {
        $input = true;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_string($result));
        $this->assertEquals('true', $result);

        $input = false;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_string($result));
        $this->assertEquals('false', $result);
    }

    public function testNull()
    {
        $input = null;
        $result = Raven_Serializer::serialize($input);
        $this->assertTrue(is_string($result));
        $this->assertEquals('null', $result);
    }

    public function testRecursionMaxDepth()
    {
        $input = array();
        $input[] = &$input;
        $result = Raven_Serializer::serialize($input, 3);
        $this->assertEquals(array(array(array('Array of length 1'))), $result);
    }
}
