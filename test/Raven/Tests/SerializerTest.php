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

class Raven_Tests_SerializerTest extends \PHPUnit\Framework\TestCase
{
    public function testArraysAreArrays()
    {
        $serializer = new Raven_Serializer();
        $input = array(1, 2, 3);
        $result = $serializer->serialize($input);
        $this->assertEquals(array('1', '2', '3'), $result);
    }

    public function testStdClassAreArrays()
    {
        $serializer = new Raven_Serializer();
        $input = new stdClass();
        $input->foo = 'BAR';
        $result = $serializer->serialize($input);
        $this->assertEquals(array('foo' => 'BAR'), $result);
    }

    public function testObjectsAreStrings()
    {
        $serializer = new Raven_Serializer();
        $input = new Raven_SerializerTestObject();
        $result = $serializer->serialize($input);
        $this->assertEquals('Object Raven_SerializerTestObject', $result);
    }

    public function testIntsAreInts()
    {
        $serializer = new Raven_Serializer();
        $input = 1;
        $result = $serializer->serialize($input);
        $this->assertInternalType('integer', $result);
        $this->assertEquals(1, $result);
    }

    public function testFloats()
    {
        $serializer = new Raven_Serializer();
        $input = 1.5;
        $result = $serializer->serialize($input);
        $this->assertInternalType('double', $result);
        $this->assertEquals(1.5, $result);
    }

    public function testBooleans()
    {
        $serializer = new Raven_Serializer();
        $input = true;
        $result = $serializer->serialize($input);
        $this->assertTrue($result);

        $input = false;
        $result = $serializer->serialize($input);
        $this->assertFalse($result);
    }

    public function testNull()
    {
        $serializer = new Raven_Serializer();
        $input = null;
        $result = $serializer->serialize($input);
        $this->assertNull($result);
    }

    public function testRecursionMaxDepth()
    {
        $serializer = new Raven_Serializer();
        $input = array();
        $input[] = &$input;
        $result = $serializer->serialize($input, 3);
        $this->assertEquals(array(array(array('Array of length 1'))), $result);
    }

    public function testObjectInArray()
    {
        $serializer = new Raven_Serializer();
        $input = array('foo' => new Raven_Serializer());
        $result = $serializer->serialize($input);
        $this->assertEquals(array('foo' => 'Object Raven_Serializer'), $result);
    }

    /**
     * @covers Raven_Serializer::serializeString
     */
    public function testBrokenEncoding()
    {
        $serializer = new Raven_Serializer();
        foreach (array('7efbce4384', 'b782b5d8e5', '9dde8d1427', '8fd4c373ca', '9b8e84cb90') as $key) {
            $input = pack('H*', $key);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            if (function_exists('mb_detect_encoding')) {
                $this->assertContains(mb_detect_encoding($result), array('ASCII', 'UTF-8'));
            }
        }
    }

    /**
     * @covers Raven_Serializer::serializeString
     */
    public function testLongString()
    {
        $serializer = new Raven_Serializer();
        for ($i = 0; $i < 100; $i++) {
            foreach (array(100, 1000, 1010, 1024, 1050, 1100, 10000) as $length) {
                $input = '';
                for ($j = 0; $j < $length; $j++) {
                    $input .= chr(mt_rand(ord('a'), ord('z')));
                }
                $result = $serializer->serialize($input);
                $this->assertInternalType('string', $result);
                $this->assertLessThanOrEqual(1024, strlen($result));
            }
        }
    }

    /**
     * @covers Raven_Serializer::serializeString
     */
    public function testLongStringWithOverwrittenMessageLength()
    {
        $serializer = new Raven_Serializer();
        $serializer->setMessageLimit(500);
        for ($i = 0; $i < 100; $i++) {
            foreach (array(100, 490, 499, 500, 501, 1000, 10000) as $length) {
                $input = '';
                for ($j = 0; $j < $length; $j++) {
                    $input .= chr(mt_rand(ord('a'), ord('z')));
                }
                $result = $serializer->serialize($input);
                $this->assertInternalType('string', $result);
                $this->assertLessThanOrEqual(500, strlen($result));
            }
        }
    }

    /**
     * @covers Raven_Serializer::serializeString
     */
    public function testLongStringWithOverwrittenMessageLengthZero()
    {
        $serializer = new Raven_Serializer();
        $serializer->setMessageLimit(0);
        for ($i = 0; $i < 100; $i++) {
            foreach (array(100, 490, 499, 500, 501, 1000, 10000) as $length) {
                $input = '';
                for ($j = 0; $j < $length; $j++) {
                    $input .= chr(mt_rand(ord('a'), ord('z')));
                }
                $result = $serializer->serialize($input);
                $this->assertInternalType('string', $result);
                $this->assertEquals($length, strlen($result));
            }
        }
    }

    /**
     * @covers Raven_Serializer::serializeValue
     */
    public function testSerializeValueResource()
    {
        $serializer = new Raven_Serializer();
        $filename = tempnam(sys_get_temp_dir(), 'sentry_test_');
        $fo = fopen($filename, 'wb');

        $result = $serializer->serialize($fo);
        $this->assertInternalType('string', $result);
        $this->assertEquals('Resource stream', $result);
    }

    public function testClippingUTF8Characters()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped('mbstring extension is not enabled.');
        }

        $teststring = 'Прекратите надеяться, что ваши пользователи будут сообщать об ошибках';
        $serializer = new Raven_Serializer(null, 19); // Length of 19 will clip character in half if no mb_* string functions are used for the teststring

        $clipped = $serializer->serialize($teststring);
        $this->assertEquals('Прекратит {clipped}', $clipped);

        Raven_Compat::json_encode($clipped);

        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }
}
