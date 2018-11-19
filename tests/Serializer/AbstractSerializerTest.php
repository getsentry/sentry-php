<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Serializer\AbstractSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\SerializerInterface;

abstract class AbstractSerializerTest extends TestCase
{
    abstract protected function getSerializerUnderTest(): AbstractSerializer;

    /**
     * This method is only existed because of testSerializeCallable.
     */
    public static function setUpBeforeClass()
    {
    }

    public function serializeAllObjectsProvider(): array
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame(['1', '2', '3'], $result);

        $result = $this->invokeSerialization($serializer, [Client::class, 'getOptions']);
        $this->assertSame([Client::class, 'getOptions'], $result);
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame(['foo' => 'BAR'], $result);
    }

    public function testObjectsAreStrings()
    {
        $serializer = $this->getSerializerUnderTest();
        $input = new SerializerTestObject();
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame('Object Sentry\Tests\SerializerTestObject', $result);
    }

    public function testObjectsAreNotStrings()
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);
        $input = new SerializerTestObject();
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame(['key' => 'value'], $result);
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame([[['Array of length 1']]], $result);

        $result = $this->invokeSerialization($serializer, []);
        $this->assertSame([], $result);

        $result = $this->invokeSerialization($serializer, [[]]);
        $this->assertSame([[]], $result);

        $result = $this->invokeSerialization($serializer, [[[]]]);
        $this->assertSame([[[]]], $result);

        $result = $this->invokeSerialization($serializer, [[[[]]]]);
        $this->assertSame([[['Array of length 0']]], $result);
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

        $result = $this->invokeSerialization($serializer, $object);

        $this->assertSame($expectedResult, $result);
        $this->assertArraySubset(['array', 'string', 'null', 'float', 'integer', 'object'], [\gettype($result)]);
    }

    /**
     * @dataProvider recursionMaxDepthForObjectDataProvider
     */
    public function testRecursionMaxDepthForObject($value, $expectedResult)
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);

        $result = $this->invokeSerialization($serializer, $value);
        $this->assertSame($expectedResult, $result);
    }

    public function recursionMaxDepthForObjectDataProvider()
    {
        return [
            [
                (object) ['key' => (object) ['key' => 12345]],
                ['key' => ['key' => 12345]],
            ],
            [
                (object) ['key' => (object) ['key' => (object) ['key' => 12345]]],
                ['key' => ['key' => ['key' => 12345]]],
            ],
            [
                (object) ['key' => (object) ['key' => (object) ['key' => (object) ['key' => 12345]]]],
                ['key' => ['key' => ['key' => 'Object stdClass']]],
            ],
        ];
    }

    public function testObjectInArray()
    {
        $serializer = $this->getSerializerUnderTest();
        $input = ['foo' => new SerializerTestObject()];

        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame(['foo' => 'Object Sentry\\Tests\\SerializerTestObject'], $result);
    }

    public function testObjectInArraySerializeAll()
    {
        $serializer = $this->getSerializerUnderTest();
        $serializer->setAllObjectSerialize(true);
        $input = ['foo' => new SerializerTestObject()];
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame(['foo' => ['key' => 'value']], $result);
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
            $result = $this->invokeSerialization($serializer, $input);
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
            $result = $this->invokeSerialization($serializer, $input);
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
            $result = $this->invokeSerialization($serializer, $input);
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
        $resource = fopen($filename, 'wb');

        $result = $this->invokeSerialization($serializer, $resource);
        $this->assertInternalType('string', $result);
        $this->assertSame('Resource stream', $result);
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
        /** @var \Sentry\Serializer\Serializer $serializer */
        $serializer = new $class_name(null, 19);

        $clipped = $serializer->serialize($testString);

        $this->assertSame('Прекратит {clipped}', $clipped);
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
                    'expected' => 'Callable void Sentry\Tests\SerializerTestObject::testy []',
                ],
            ];
            require_once '../resources/php70_serializing.inc';

            if (version_compare(PHP_VERSION, '7.1.0') >= 0) {
                require_once '../resources/php71_serializing.inc';
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
                'expected' => 'Callable void Sentry\Tests\SerializerTestObject::testy []',
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
        $actual = $this->invokeSerialization($serializer, $callable);

        $this->assertSame($expected, $actual);

        $actual = $this->invokeSerialization($serializer, [$callable]);

        $this->assertSame([$expected], $actual);
    }

    /**
     * @param AbstractSerializer $serializer
     * @param mixed              $input
     *
     * @return string|bool|float|int|null|array|object
     */
    protected function invokeSerialization(AbstractSerializer $serializer, $input)
    {
        if ($serializer instanceof SerializerInterface) {
            return $serializer->serialize($input);
        }

        if ($serializer instanceof RepresentationSerializerInterface) {
            return $serializer->representationSerialize($input);
        }

        throw new \InvalidArgumentException('Unrecognized AbstractSerializer: ' . \get_class($serializer));
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

    public static function testy(): void
    {
        throw new \Exception('We should not reach this');
    }
}
