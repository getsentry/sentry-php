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

use PHPUnit\Framework\TestCase;

abstract class SerializerAbstractTest extends TestCase
{
    /**
     * @return string|\Raven\Serializer
     */
    protected static function get_test_class()
    {
        return '';
    }

    public function dataGetBaseParam()
    {
        return [
            ['serialize_all_objects' => false],
            ['serialize_all_objects' => true],
        ];
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testArraysAreArrays($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = [1, 2, 3];
        $result = $serializer->serialize($input);
        $this->assertEquals(['1', '2', '3'], $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testStdClassAreArrays($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = new \stdClass();
        $input->foo = 'BAR';
        $result = $serializer->serialize($input);
        $this->assertEquals(['foo' => 'BAR'], $result);
    }

    public function testObjectsAreStrings()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $input = new \Raven\Tests\SerializerTestObject();
        $result = $serializer->serialize($input);
        $this->assertEquals('Object Raven\Tests\SerializerTestObject', $result);
    }

    public function testObjectsAreNotStrings()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $serializer->setAllObjectSerialize(true);
        $input = new \Raven\Tests\SerializerTestObject();
        $result = $serializer->serialize($input);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testIntsAreInts($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = 1;
        $result = $serializer->serialize($input);
        $this->assertInternalType('integer', $result);
        $this->assertEquals(1, $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testFloats($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = 1.5;
        $result = $serializer->serialize($input);
        $this->assertInternalType('double', $result);
        $this->assertEquals(1.5, $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testBooleans($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = true;
        $result = $serializer->serialize($input);
        $this->assertTrue($result);

        $input = false;
        $result = $serializer->serialize($input);
        $this->assertFalse($result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testNull($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = null;
        $result = $serializer->serialize($input);
        $this->assertNull($result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testRecursionMaxDepth($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = [];
        $input[] = &$input;
        $result = $serializer->serialize($input, 3);
        $this->assertEquals([[['Array of length 1']]], $result);

        $result = $serializer->serialize([], 3);
        $this->assertEquals([], $result);

        $result = $serializer->serialize([[]], 3);
        $this->assertEquals([[]], $result);

        $result = $serializer->serialize([[[]]], 3);
        $this->assertEquals([[[]]], $result);

        $result = $serializer->serialize([[[[]]]], 3);
        $this->assertEquals([[['Array of length 0']]], $result);
    }

    public function dataRecursionInObjects()
    {
        $data = [];
        // case 1
        $object = new SerializerTestObject();
        $object->key = $object;
        $data[] = [
            'object' => $object,
            'result_serialize' => ['key' => 'Object Raven\Tests\SerializerTestObject'],
        ];

        // case 2
        $object = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = $object;
        $object->key = $object2;
        $data[] = [
            'object' => $object,
            'result_serialize' => ['key' => ['key' => 'Object Raven\Tests\SerializerTestObject']],
        ];

        // case 3
        $object = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = 'foobar';
        $object->key = $object2;
        $data[] = [
            'object' => $object,
            'result_serialize' => ['key' => ['key' => 'foobar']],
        ];

        // case 4
        $object3 = new SerializerTestObject();
        $object3->key = 'foobar';
        $object2 = new SerializerTestObject();
        $object2->key = $object3;
        $object = new SerializerTestObject();
        $object->key = $object2;
        $data[] = [
            'object' => $object,
            'result_serialize' => ['key' => ['key' => ['key' => 'foobar']]],
        ];

        // case 5
        $object4 = new SerializerTestObject();
        $object4->key = 'foobar';
        $object3 = new SerializerTestObject();
        $object3->key = $object4;
        $object2 = new SerializerTestObject();
        $object2->key = $object3;
        $object = new SerializerTestObject();
        $object->key = $object2;
        $data[] = [
            'object' => $object,
            'result_serialize' => ['key' => ['key' => ['key' => 'Object Raven\\Tests\\SerializerTestObject']]],
        ];

        // case 6
        $object3 = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = $object3;
        $object2->keys = 'keys';
        $object = new SerializerTestObject();
        $object->key = $object2;
        $object3->key = $object2;
        $data[] = [
            'object' => $object,
            'result_serialize' => ['key' => ['key' => ['key' => 'Object Raven\\Tests\\SerializerTestObject'],
                                             'keys' => 'keys', ]],
        ];

        foreach ($data as &$datum) {
            if (!isset($datum['result_serialize_object'])) {
                $datum['result_serialize_object'] = $datum['result_serialize'];
            }
        }

        return $data;
    }

    /**
     * @param object $object
     * @param array  $result_serialize
     * @param array  $result_serialize_object
     *
     * @dataProvider dataRecursionInObjects
     */
    public function testRecursionInObjects($object, $result_serialize, $result_serialize_object)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $serializer->setAllObjectSerialize(true);

        $result1 = $serializer->serialize($object, 3);
        $result2 = $serializer->serializeObject($object, 3);
        $this->assertEquals($result_serialize, $result1);
        $this->assertEquals($result_serialize_object, $result2);
    }

    public function testRecursionMaxDepthForObject()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $serializer->setAllObjectSerialize(true);

        $result = $serializer->serialize((object) ['key' => (object) ['key' => 12345]], 3);
        $this->assertEquals(['key' => ['key' => 12345]], $result);

        $result = $serializer->serialize((object) ['key' => (object) ['key' => (object) ['key' => 12345]]], 3);
        $this->assertEquals(['key' => ['key' => ['key' => 12345]]], $result);

        $result = $serializer->serialize(
            (object) ['key' => (object) ['key' => (object) ['key' => (object) ['key' => 12345]]]],
            3
        );
        $this->assertEquals(['key' => ['key' => ['key' => 'Object stdClass']]], $result);
    }

    public function testObjectInArray()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $input = ['foo' => new \Raven\Tests\SerializerTestObject()];
        $result = $serializer->serialize($input);
        $this->assertEquals(['foo' => 'Object Raven\\Tests\\SerializerTestObject'], $result);
    }

    public function testObjectInArraySerializeAll()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $serializer->setAllObjectSerialize(true);
        $input = ['foo' => new \Raven\Tests\SerializerTestObject()];
        $result = $serializer->serialize($input);
        $this->assertEquals(['foo' => ['key' => 'value']], $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testBrokenEncoding($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        foreach (['7efbce4384', 'b782b5d8e5', '9dde8d1427', '8fd4c373ca', '9b8e84cb90'] as $key) {
            $input = pack('H*', $key);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            if (function_exists('mb_detect_encoding')) {
                $this->assertContains(mb_detect_encoding($result), ['ASCII', 'UTF-8']);
            }
        }
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testLongString($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }

        foreach ([100, 1000, 1010, 1024, 1050, 1100, 10000] as $length) {
            $input = str_repeat('x', $length);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            $this->assertLessThanOrEqual(1024, strlen($result));
        }
    }

    public function testLongStringWithOverwrittenMessageLength()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $serializer->setMessageLimit(500);

        foreach (array(100, 490, 499, 500, 501, 1000, 10000) as $length) {
            $input = str_repeat('x', $length);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            $this->assertLessThanOrEqual(500, strlen($result));
        }
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testSerializeValueResource($serialize_all_objects)
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $filename = tempnam(sys_get_temp_dir(), 'sentry_test_');
        $fo = fopen($filename, 'wb');

        $result = $serializer->serialize($fo);
        $this->assertInternalType('string', $result);
        $this->assertEquals('Resource stream', $result);
    }

    public function testSetAllObjectSerialize()
    {
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name();
        $serializer->setAllObjectSerialize(true);
        $this->assertTrue($serializer->getAllObjectSerialize());
        $serializer->setAllObjectSerialize(false);
        $this->assertFalse($serializer->getAllObjectSerialize());
    }

    public function testClippingUTF8Characters()
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped('mbstring extension is not enabled.');
        }

        $testString = 'Прекратите надеяться, что ваши пользователи будут сообщать об ошибках';
        $class_name = static::get_test_class();
        /** @var \Raven\Serializer $serializer */
        $serializer = new $class_name(null, 19);

        $clipped = $serializer->serialize($testString);

        $this->assertEquals('Прекратит {clipped}', $clipped);
        $this->assertNotNull(json_encode($clipped));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}

/**
 * Class SerializerTestObject.
 *
 * @property mixed $keys
 */
class SerializerTestObject
{
    private $foo = 'bar';

    public $key = 'value';
}
