<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Client;

abstract class AbstractSerializerTest extends TestCase
{
    /**
     * @return \Sentry\Serializer
     */
    abstract protected function getSerializerUnderTest();

    /**
     * This method is only existed because of testSerializeCallable.
     */
    public static function setUpBeforeClass()
    {
    }

    public function serializeAllObjectsProvider()
    {
        return [
            ['serializeAllObjects' => false],
            ['serializeAllObjects' => true],
        ];
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testArraysAreArrays($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = [1, 2, 3];
        $result = $serializer->serialize($input);
        $this->assertEquals(['1', '2', '3'], $result);

        $result = $serializer->serialize([Client::class, 'getOptions']);
        $this->assertEquals([Client::class, 'getOptions'], $result);
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testStdClassAreArrays($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = new \stdClass();
        $input->foo = 'BAR';
        $result = $serializer->serialize($input);
        $this->assertEquals(['foo' => 'BAR'], $result);
    }

    public function testObjectsAreStrings()
    {
        $serializer = $this->getSerializerUnderTest();
        $input = new SerializerTestObject();
        $result = $serializer->serialize($input);
        $this->assertEquals('Object Sentry\Tests\SerializerTestObject', $result);
    }

    public function testObjectsAreNotStrings()
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);
        $input = new SerializerTestObject();
        $result = $serializer->serialize($input);
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testIntsAreInts($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = 1;
        $result = $serializer->serialize($input);
        $this->assertInternalType('integer', $result);
        $this->assertEquals(1, $result);
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testFloats($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = 1.5;
        $result = $serializer->serialize($input);
        $this->assertInternalType('double', $result);
        $this->assertEquals(1.5, $result);
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testBooleans($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
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
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testNull($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = null;
        $result = $serializer->serialize($input);
        $this->assertNull($result);
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testRecursionMaxDepth($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
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

    public function dataRecursionInObjectsDataProvider()
    {
        $object = new SerializerTestObject();
        $object->key = $object;
        yield [
            'object' => $object,
            'expectedResult' => ['key' => 'Object Sentry\Tests\SerializerTestObject'],
        ];

        $object = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = $object;
        $object->key = $object2;
        yield [
            'object' => $object,
            'expectedResult' => ['key' => ['key' => 'Object Sentry\Tests\SerializerTestObject']],
        ];

        $object = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = 'foobar';
        $object->key = $object2;
        yield [
            'object' => $object,
            'expectedResult' => ['key' => ['key' => 'foobar']],
        ];

        $object3 = new SerializerTestObject();
        $object3->key = 'foobar';
        $object2 = new SerializerTestObject();
        $object2->key = $object3;
        $object = new SerializerTestObject();
        $object->key = $object2;
        yield [
            'object' => $object,
            'expectedResult' => ['key' => ['key' => ['key' => 'foobar']]],
        ];

        $object4 = new SerializerTestObject();
        $object4->key = 'foobar';
        $object3 = new SerializerTestObject();
        $object3->key = $object4;
        $object2 = new SerializerTestObject();
        $object2->key = $object3;
        $object = new SerializerTestObject();
        $object->key = $object2;
        yield [
            'object' => $object,
            'expectedResult' => ['key' => ['key' => ['key' => 'Object Sentry\\Tests\\SerializerTestObject']]],
        ];

        $object3 = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = $object3;
        $object2->keys = 'keys';
        $object = new SerializerTestObject();
        $object->key = $object2;
        $object3->key = $object2;
        yield [
            'object' => $object,
            'expectedResult' => ['key' => ['key' => ['key' => 'Object Sentry\\Tests\\SerializerTestObject'], 'keys' => 'keys']],
        ];
    }

    /**
     * @param object $object
     * @param array  $expectedResult
     *
     * @dataProvider dataRecursionInObjectsDataProvider
     */
    public function testRecursionInObjects($object, $expectedResult)
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);

        $result1 = $serializer->serialize($object, 3);
        $result2 = $serializer->serializeObject($object, 3);
        $this->assertEquals($expectedResult, $result1);
        $this->assertContains(\gettype($result1), ['array', 'string', 'null', 'float', 'integer', 'object']);
        $this->assertEquals($expectedResult, $result2);
        $this->assertContains(\gettype($result2), ['array', 'string']);
    }

    public function testRecursionMaxDepthForObject()
    {
        $serializer = $this->getSerializerUnderTest();
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
        $serializer = $this->getSerializerUnderTest();
        $input = ['foo' => new SerializerTestObject()];
        $result = $serializer->serialize($input);
        $this->assertEquals(['foo' => 'Object Sentry\\Tests\\SerializerTestObject'], $result);
    }

    public function testObjectInArraySerializeAll()
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);
        $input = ['foo' => new SerializerTestObject()];
        $result = $serializer->serialize($input);
        $this->assertEquals(['foo' => ['key' => 'value']], $result);
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testBrokenEncoding($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        foreach (['7efbce4384', 'b782b5d8e5', '9dde8d1427', '8fd4c373ca', '9b8e84cb90'] as $key) {
            $input = pack('H*', $key);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            if (\function_exists('mb_detect_encoding')) {
                $this->assertContains(mb_detect_encoding($result), ['ASCII', 'UTF-8']);
            }
        }
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testLongString($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }

        foreach ([100, 1000, 1010, 1024, 1050, 1100, 10000] as $length) {
            $input = str_repeat('x', $length);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            $this->assertLessThanOrEqual(1024, \strlen($result));
        }
    }

    public function testLongStringWithOverwrittenMessageLength()
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setMessageLimit(500);

        foreach ([100, 490, 499, 500, 501, 1000, 10000] as $length) {
            $input = str_repeat('x', $length);
            $result = $serializer->serialize($input);
            $this->assertInternalType('string', $result);
            $this->assertLessThanOrEqual(500, \strlen($result));
        }
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testSerializeValueResource($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setAllObjectSerialize(true);
        }
        $filename = tempnam(sys_get_temp_dir(), 'sentry_test_');
        $this->assertNotFalse($filename, 'Temp file creation failed');
        $fo = fopen($filename, 'wb');

        $result = $serializer->serialize($fo);
        $this->assertInternalType('string', $result);
        $this->assertEquals('Resource stream', $result);
    }

    public function testSetAllObjectSerialize()
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);
        $this->assertTrue($serializer->getAllObjectSerialize());
        $serializer->setAllObjectSerialize(false);
        $this->assertFalse($serializer->getAllObjectSerialize());
    }

    public function testClippingUTF8Characters()
    {
        if (!\extension_loaded('mbstring')) {
            $this->markTestSkipped('mbstring extension is not enabled.');
        }

        $testString = 'Прекратите надеяться, что ваши пользователи будут сообщать об ошибках';
        $class_name = static::getSerializerUnderTest();
        /** @var \Sentry\Serializer $serializer */
        $serializer = new $class_name(null, 19);

        $clipped = $serializer->serialize($testString);

        $this->assertEquals('Прекратит {clipped}', $clipped);
        $this->assertNotNull(json_encode($clipped));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function serializableCallableProvider()
    {
        $closure1 = function (array $param1) {
            return $param1 * 2;
        };
        $closure2 = function ($param1a) {
            throw new \Exception('Don\'t even think about invoke me');
        };
        $closure4 = function (callable $param1c) {
            throw new \Exception('Don\'t even think about invoke me');
        };
        $closure5 = function (\stdClass $param1d) {
            throw new \Exception('Don\'t even think about invoke me');
        };
        $closure6 = function (\stdClass $param1e = null) {
            throw new \Exception('Don\'t even think about invoke me');
        };
        $closure7 = function (array &$param1f) {
            throw new \Exception('Don\'t even think about invoke me');
        };
        $closure8 = function (array &$param1g = null) {
            throw new \Exception('Don\'t even think about invoke me');
        };

        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $data = [
                [
                    'callable' => $closure1,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [array param1]',
                ], [
                    'callable' => $closure2,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [mixed|null param1a]',
                ], [
                    'callable' => $closure4,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [callable param1c]',
                ], [
                    'callable' => $closure5,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [stdClass param1d]',
                ], [
                    'callable' => $closure6,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [stdClass|null [param1e]]',
                ], [
                    'callable' => $closure7,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [array &param1f]',
                ], [
                    'callable' => $closure8,
                    'expected' => 'Lambda Sentry\\Tests\\{closure} [array|null [&param1g]]',
                ], [
                    'callable' => [$this, 'serializableCallableProvider'],
                    'expected' => 'Callable Sentry\Tests\AbstractSerializerTest::serializableCallableProvider []',
                ], [
                    'callable' => [TestCase::class, 'setUpBeforeClass'],
                    'expected' => 'Callable PHPUnit\\Framework\\TestCase::setUpBeforeClass []',
                ], [
                    'callable' => [$this, 'setUpBeforeClass'],
                    'expected' => 'Callable Sentry\Tests\AbstractSerializerTest::setUpBeforeClass []',
                ], [
                    'callable' => [self::class, 'setUpBeforeClass'],
                    'expected' => 'Callable Sentry\Tests\AbstractSerializerTest::setUpBeforeClass []',
                ], [
                    'callable' => [SerializerTestObject::class, 'testy'],
                    'expected' => 'Callable array Sentry\Tests\SerializerTestObject::testy []',
                ],
            ];
            require_once 'resources/php70_serializing.inc';

            if (version_compare(PHP_VERSION, '7.1.0') >= 0) {
                require_once 'resources/php71_serializing.inc';
            }

            return $data;
        }

        return [
            [
                'callable' => $closure1,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [array param1]',
            ], [
                'callable' => $closure2,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [param1a]',
            ], [
                'callable' => $closure4,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [callable param1c]',
            ], [
                'callable' => $closure5,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [param1d]',
            ], [
                'callable' => $closure6,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [[param1e]]',
            ], [
                'callable' => $closure7,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [array &param1f]',
            ], [
                'callable' => $closure8,
                'expected' => 'Lambda Sentry\\Tests\\{closure} [array|null [&param1g]]',
            ], [
                'callable' => [$this, 'serializableCallableProvider'],
                'expected' => 'Callable Sentry\Tests\AbstractSerializerTest::serializableCallableProvider []',
            ], [
                'callable' => [TestCase::class, 'setUpBeforeClass'],
                'expected' => 'Callable PHPUnit_Framework_TestCase::setUpBeforeClass []',
            ], [
                'callable' => [$this, 'setUpBeforeClass'],
                'expected' => 'Callable Sentry\Tests\AbstractSerializerTest::setUpBeforeClass []',
            ], [
                'callable' => [self::class, 'setUpBeforeClass'],
                'expected' => 'Callable Sentry\Tests\AbstractSerializerTest::setUpBeforeClass []',
            ], [
                'callable' => [SerializerTestObject::class, 'testy'],
                'expected' => 'Callable array Sentry\Tests\SerializerTestObject::testy []',
            ],
        ];
    }

    /**
     * @param callable $callable
     * @param string   $expected
     *
     * @dataProvider serializableCallableProvider
     */
    public function testSerializeCallable($callable, $expected)
    {
        $serializer = $this->getSerializerUnderTest();
        $actual = $serializer->serializeCallable($callable);
        $this->assertEquals($expected, $actual);

        $actual2 = $serializer->serialize($callable);
        $actual3 = $serializer->serialize([$callable]);
        if (\is_array($callable)) {
            $this->assertInternalType('array', $actual2);
            $this->assertInternalType('array', $actual3);
        } else {
            $this->assertEquals($expected, $actual2);
            $this->assertEquals([$expected], $actual3);
        }
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

    public static function testy(): array
    {
        return [];
    }
}
