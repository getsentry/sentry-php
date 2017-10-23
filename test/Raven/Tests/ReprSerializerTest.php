<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_ReprSerializerTest extends \PHPUnit\Framework\TestCase
{
    public function testArraysAreArrays()
    {
        $serializer = new Raven_ReprSerializer();
        $input = array(1, 2, 3);
        $result = $serializer->serialize($input);
        $this->assertEquals(array('1', '2', '3'), $result);
    }

    public function testObjectsAreStrings()
    {
        $serializer = new Raven_ReprSerializer();
        $input = new Raven_StacktraceTestObject();
        $result = $serializer->serialize($input);
        $this->assertEquals('Object Raven_StacktraceTestObject', $result);
    }

    public function testIntsAreInts()
    {
        $serializer = new Raven_ReprSerializer();
        $input = 1;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals(1, $result);
    }

    public function testFloats()
    {
        $serializer = new Raven_ReprSerializer();
        $input = 1.5;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals('1.5', $result);
    }

    public function testBooleans()
    {
        $serializer = new Raven_ReprSerializer();
        $input = true;
        $result = $serializer->serialize($input);
        $this->assertEquals('true', $result);

        $input = false;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals('false', $result);
    }

    public function testNull()
    {
        $serializer = new Raven_ReprSerializer();
        $input = null;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals('null', $result);
    }

    public function testRecursionMaxDepth()
    {
        $serializer = new Raven_ReprSerializer();
        $input = array();
        $input[] = &$input;
        $result = $serializer->serialize($input, 3);
        $this->assertEquals(array(array(array('Array of length 1'))), $result);
    }

    /**
     * @covers Raven_ReprSerializer::serializeValue
     */
    public function testSerializeValueResource()
    {
        $serializer = new Raven_ReprSerializer();
        $filename = tempnam(sys_get_temp_dir(), 'sentry_test_');
        $fo = fopen($filename, 'wb');

        $result = $serializer->serialize($fo);
        $this->assertInternalType('string', $result);
        $this->assertEquals('Resource stream', $result);
    }

    /**
     * @covers Raven_ReprSerializer::serializeValue
     */
    public function testSerializeRoundedFloat()
    {
        $serializer = new Raven_ReprSerializer();

        $result = $serializer->serialize((double)1);
        $this->assertInternalType('string', $result);
        $this->assertEquals('1.0', $result);

        $result = $serializer->serialize((double)floor(5 / 2));
        $this->assertInternalType('string', $result);
        $this->assertEquals('2.0', $result);

        $result = $serializer->serialize((double)floor(12345.678901234));
        $this->assertInternalType('string', $result);
        $this->assertEquals('12345.0', $result);
    }
}
