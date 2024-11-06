<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Sentry\Serializer\AbstractSerializer;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\SerializerInterface;

abstract class AbstractSerializerTest extends TestCase
{
    abstract protected function createSerializer(): AbstractSerializer;

    /**
     * This method is only existed because of testSerializeCallable.
     */
    public static function setUpBeforeClass(): void
    {
    }

    public static function serializeAllObjectsDataProvider(): array
    {
        return [
            ['serializeAllObjects' => false],
            ['serializeAllObjects' => true],
        ];
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testStdClassAreArrays(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $input = ['foo' => 'BAR'];
        $result = $this->invokeSerialization($serializer, (object) $input);

        $this->assertSame($input, $result);
    }

    public function testObjectsAreStrings(): void
    {
        $serializer = $this->createSerializer();
        $input = new SerializerTestObject();
        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame('Object Sentry\Tests\Serializer\SerializerTestObject', $result);
    }

    public static function objectsWithIdPropertyDataProvider(): array
    {
        return [
            ['bar', 'Object Sentry\Tests\Serializer\SerializerTestObjectWithIdProperty(#bar)'],
            [123, 'Object Sentry\Tests\Serializer\SerializerTestObjectWithIdProperty(#123)'],
            [[1, 2, 3], 'Object Sentry\Tests\Serializer\SerializerTestObjectWithIdProperty'],
            [(object) ['id' => 321], 'Object Sentry\Tests\Serializer\SerializerTestObjectWithIdProperty'],
            [null, 'Object Sentry\Tests\Serializer\SerializerTestObjectWithIdProperty'],
        ];
    }

    /**
     * @dataProvider objectsWithIdPropertyDataProvider
     */
    public function testObjectsWithIdPropertyAreStrings($id, string $expectedResult): void
    {
        $serializer = $this->createSerializer();
        $input = new SerializerTestObjectWithIdProperty();
        $input->id = $id;
        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame($expectedResult, $result);
    }

    public function testObjectsWithMagicIdPropertyDoesNotInvokeMagicMethods(): void
    {
        $serializer = $this->createSerializer();
        $input = new SerializerTestObjectWithMagicGetAndIssetMethods();
        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame('Object Sentry\Tests\Serializer\SerializerTestObjectWithMagicGetAndIssetMethods', $result);
    }

    public function testObjectsWithSerializerTestObjectWithGetIdMethodAreStrings(): void
    {
        $serializer = $this->createSerializer();
        $input = new SerializerTestObjectWithGetIdMethod();
        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame('Object Sentry\Tests\Serializer\SerializerTestObjectWithGetIdMethod(#foobar)', $result);
    }

    public function testObjectsAreNotStrings(): void
    {
        $serializer = $this->createSerializer();
        $serializer->setSerializeAllObjects(true);

        $input = new SerializerTestObject();
        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame(['key' => 'value'], $result);
    }

    /**
     * @dataProvider iterableDataProvider
     */
    public function testIterablesAreNotConsumed(iterable $iterable, array $input): void
    {
        $serializer = $this->createSerializer();
        $output = [];

        foreach ($iterable as $k => $v) {
            $output[$k] = $v;

            $this->invokeSerialization($serializer, $iterable);
        }

        $this->assertSame($input, $output);
    }

    public static function iterableDataProvider(): \Generator
    {
        yield [
            'iterable' => ['value1', 'value2'],
            'input' => ['value1', 'value2'],
        ];

        yield [
            'iterable' => new \ArrayIterator(['value1', 'value2']),
            'input' => ['value1', 'value2'],
        ];

        // Also test with a non-rewindable non-cloneable iterator:
        yield [
            'iterable' => (static function (): \Generator {
                yield 'value1';
                yield 'value2';
            })(),
            'input' => [
                'value1',
                'value2',
            ],
        ];
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testRecursionMaxDepth(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
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

    public static function dataRecursionInObjectsDataProvider(): \Generator
    {
        $object = new SerializerTestObject();
        $object->key = $object;

        yield [
            'object' => $object,
            'expectedResult' => ['key' => 'Object ' . SerializerTestObject::class],
        ];

        $object = new SerializerTestObject();
        $object2 = new SerializerTestObject();
        $object2->key = $object;
        $object->key = $object2;

        yield [
            'object' => $object,
            'expectedResult' => ['key' => ['key' => 'Object ' . SerializerTestObject::class]],
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
            'expectedResult' => ['key' => ['key' => ['key' => 'Object ' . SerializerTestObject::class]]],
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
            'expectedResult' => [
                'key' => [
                    'key' => ['key' => 'Object ' . SerializerTestObject::class],
                    'keys' => 'keys',
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataRecursionInObjectsDataProvider
     */
    public function testRecursionInObjects($object, array $expectedResult): void
    {
        $serializer = $this->createSerializer();
        $serializer->setSerializeAllObjects(true);

        $result = $this->invokeSerialization($serializer, $object);

        $this->assertSame($expectedResult, $result);
        $this->assertContains(\gettype($result), ['array', 'string', 'null', 'float', 'integer', 'object']);
    }

    /**
     * @dataProvider recursionMaxDepthForObjectDataProvider
     */
    public function testRecursionMaxDepthForObject($value, $expectedResult): void
    {
        $serializer = $this->createSerializer();
        $serializer->setSerializeAllObjects(true);

        $result = $this->invokeSerialization($serializer, $value);

        $this->assertEquals($expectedResult, $result);
    }

    public static function recursionMaxDepthForObjectDataProvider(): array
    {
        return [
            [
                (object) [
                    'key' => (object) [
                        'key' => 12345,
                    ],
                ],
                [
                    'key' => [
                        'key' => 12345,
                    ],
                ],
            ],
            [
                (object) [
                    'key' => (object) [
                        'key' => (object) [
                            'key' => 12345,
                        ],
                    ],
                ],
                [
                    'key' => [
                        'key' => [
                            'key' => 12345,
                        ],
                    ],
                ],
            ],
            [
                (object) [
                    'key' => (object) [
                        'key' => (object) [
                            'key' => (object) [
                                'key' => 12345,
                            ],
                        ],
                    ],
                ],
                [
                    'key' => [
                        'key' => [
                            'key' => 'Object stdClass',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testObjectInArray(): void
    {
        $serializer = $this->createSerializer();
        $input = ['foo' => new SerializerTestObject()];

        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame(['foo' => 'Object ' . SerializerTestObject::class], $result);
    }

    public function testObjectInArraySerializeAll(): void
    {
        $serializer = $this->createSerializer();
        $serializer->setSerializeAllObjects(true);
        $input = ['foo' => new SerializerTestObject()];

        $result = $this->invokeSerialization($serializer, $input);

        $this->assertSame(['foo' => ['key' => 'value']], $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testBrokenEncoding(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        foreach (['7efbce4384', 'b782b5d8e5', '9dde8d1427', '8fd4c373ca', '9b8e84cb90'] as $key) {
            $input = pack('H*', $key);
            $result = $this->invokeSerialization($serializer, $input);

            $this->assertIsString($result);

            if (\function_exists('mb_detect_encoding')) {
                $this->assertContains(mb_detect_encoding($result), ['ASCII', 'UTF-8']);
            }
        }
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testLongString(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        foreach ([100, 1000, 1010, 1024, 1050, 1100, 10000] as $length) {
            $input = str_repeat('x', $length);
            $result = $this->invokeSerialization($serializer, $input);

            $this->assertIsString($result);
            $this->assertLessThanOrEqual(1024, \strlen($result));
        }
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testSerializeValueResource(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $filename = tempnam(sys_get_temp_dir(), 'sentry_test_');

        $this->assertNotFalse($filename, 'Temp file creation failed');

        $resource = fopen($filename, 'w');

        $result = $this->invokeSerialization($serializer, $resource);

        $this->assertIsString($result);
        $this->assertSame('Resource stream', $result);
    }

    public function testSetAllObjectSerialize(): void
    {
        $serializer = $this->createSerializer();
        $serializer->setSerializeAllObjects(true);

        $this->assertTrue($serializer->getSerializeAllObjects());

        $serializer->setSerializeAllObjects(false);

        $this->assertFalse($serializer->getSerializeAllObjects());
    }

    public function serializableCallableProvider(): array
    {
        $filename = \dirname(__DIR__) . '/resources/callable_without_namespace.inc';
        $this->assertFileExists($filename);
        $callableWithoutNamespaces = require $filename;

        $prettyClosureNames = \PHP_VERSION_ID >= 80400;

        return [
            [
                'callable' => function (array $param1) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [array param1]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [array param1]',
            ],
            [
                'callable' => function ($param1a) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [mixed|null param1a]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [mixed|null param1a]',
            ],
            [
                'callable' => function (callable $param1c) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [callable param1c]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [callable param1c]',
            ],
            [
                'callable' => function (\stdClass $param1d) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [stdClass param1d]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [stdClass param1d]',
            ],
            [
                'callable' => function (?\stdClass $param1e = null) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [stdClass|null [param1e]]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [stdClass|null [param1e]]',
            ],
            [
                'callable' => function (array &$param1f) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [array &param1f]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [array &param1f]',
            ],
            [
                'callable' => function (?array &$param1g = null) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [array|null [&param1g]]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [array|null [&param1g]]',
            ],
            [
                'callable' => [$this, 'serializableCallableProvider'],
                'expected' => 'Callable array ' . __CLASS__ . '::serializableCallableProvider []',
            ],
            [
                'callable' => [TestCase::class, 'setUpBeforeClass'],
                'expected' => 'Callable void PHPUnit\\Framework\\TestCase::setUpBeforeClass []',
            ],
            [
                'callable' => [$this, 'setUpBeforeClass'],
                'expected' => 'Callable void ' . __CLASS__ . '::setUpBeforeClass []',
            ],
            [
                'callable' => [self::class, 'setUpBeforeClass'],
                'expected' => 'Callable void ' . __CLASS__ . '::setUpBeforeClass []',
            ],
            [
                'callable' => [SerializerTestObject::class, 'testy'],
                'expected' => 'Callable void ' . SerializerTestObject::class . '::testy []',
            ],
            [
                'callable' => function (int $param1_70a) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [int param1_70a]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [int param1_70a]',
            ],
            [
                'callable' => function (&$param): int {
                    return (int) $param;
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda int {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [mixed|null &param]'
                    : 'Lambda int ' . __NAMESPACE__ . '\\{closure} [mixed|null &param]',
            ],
            [
                'callable' => function (int $param): ?int {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda int {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [int param]'
                    : 'Lambda int ' . __NAMESPACE__ . '\\{closure} [int param]',
            ],
            [
                'callable' => function (?int $param1_70b) {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [int|null param1_70b]'
                    : 'Lambda ' . __NAMESPACE__ . '\\{closure} [int|null param1_70b]',
            ],
            [
                'callable' => function (?int $param1_70c): void {
                    throw new \Exception('Don\'t even think about invoke me');
                },
                'expected' => $prettyClosureNames
                    ? 'Lambda void {closure:' . __CLASS__ . '::' . __FUNCTION__ . '():%d} [int|null param1_70c]'
                    : 'Lambda void ' . __NAMESPACE__ . '\\{closure} [int|null param1_70c]',
            ],
            [
                'callable' => $callableWithoutNamespaces,
                'expected' => $prettyClosureNames
                    ? 'Lambda void {closure:%s:%d} [int|null param1_70ns]'
                    : 'Lambda void {closure} [int|null param1_70ns]',
            ],
            [
                // This is (a example of) a PHP provided function that is technically callable but we want to ignore that because it causes more false positives than it helps
                'callable' => 'header',
                'expected' => 'header',
            ],
            [
                'callable' => __METHOD__,
                'expected' => __METHOD__,
            ],
        ];
    }

    /**
     * @dataProvider serializableCallableProvider
     */
    public function testSerializeCallable($callable, string $expected): void
    {
        $serializer = $this->createSerializer();
        $actual = $this->invokeSerialization($serializer, $callable);

        $this->assertStringMatchesFormat($expected, $actual);

        $actual = $this->invokeSerialization($serializer, [$callable]);

        $this->assertStringMatchesFormat($expected, $actual[0]);
    }

    /**
     * @dataProvider serializationForBadStringsDataProvider
     */
    public function testSerializationForBadStrings(string $string, string $expected, ?string $mbDetectOrder = null): void
    {
        $serializer = $this->createSerializer();

        if ($mbDetectOrder) {
            $serializer->setMbDetectOrder($mbDetectOrder);
        }

        $this->assertSame($expected, $this->invokeSerialization($serializer, $string));
    }

    public static function serializationForBadStringsDataProvider(): array
    {
        $utf8String = 'äöü';

        return [
            [mb_convert_encoding($utf8String, 'ISO-8859-1', 'UTF-8'), $utf8String, 'ISO-8859-1, ASCII, UTF-8'],
            ["\xC2\xA2\xC2", "\xC2\xA2\x3F"], // ill-formed 2-byte character U+00A2 (CENT SIGN)
        ];
    }

    public function testSerializationOfInvokable(): void
    {
        $serializer = $this->createSerializer();
        $this->assertSame('Callable bool Sentry\Tests\Serializer\Invokable::__invoke []', $this->invokeSerialization($serializer, new Invokable()));
    }

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

#[\AllowDynamicProperties]
class SerializerTestObject
{
    private $foo = 'bar';

    public $key = 'value';

    public static function testy(): void
    {
        throw new \Exception('We should not reach this');
    }
}

class SerializerTestObjectWithIdProperty extends SerializerTestObject
{
    public $id = 'bar';
}

class SerializerTestObjectWithGetIdMethod extends SerializerTestObject
{
    private $id = 'foo';

    public function getId()
    {
        return $this->id . 'bar';
    }
}

class SerializerTestObjectWithMagicGetAndIssetMethods
{
    public function __isset(string $name): bool
    {
        throw new \RuntimeException('We should not reach this!');
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        throw new \RuntimeException('We should not reach this!');
    }

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        throw new \RuntimeException('We should not reach this!');
    }
}

class Invokable
{
    public function __invoke(): bool
    {
        return true;
    }
}
