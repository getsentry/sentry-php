<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use Sentry\Options;
use Sentry\Serializer\AbstractSerializer;
use Sentry\Serializer\SerializableInterface;
use Sentry\Serializer\Serializer;

final class SerializerTest extends AbstractSerializerTest
{
    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testArraysAreArrays(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $this->invokeSerialization($serializer, [1, 2, 3]);

        $this->assertSame([1, 2, 3], $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testIntsAreInts(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $this->invokeSerialization($serializer, 1);

        $this->assertInternalType('integer', $result);
        $this->assertSame(1, $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testFloats(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $this->invokeSerialization($serializer, 1.5);

        $this->assertInternalType('double', $result);
        $this->assertSame(1.5, $result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testBooleans(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $this->invokeSerialization($serializer, true);

        $this->assertTrue($result);

        $result = $this->invokeSerialization($serializer, false);

        $this->assertFalse($result);
    }

    /**
     * @dataProvider serializeAllObjectsDataProvider
     */
    public function testNull(bool $serializeAllObjects): void
    {
        $serializer = $this->createSerializer();

        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $this->invokeSerialization($serializer, null);

        $this->assertNull($result);
    }

    public function testRegisteredObjectSerializers(): void
    {
        $object = new class() {
            public function getPurpose(): string
            {
                return 'To be tested!';
            }
        };

        $objectClass = \get_class($object);

        $serializer = $this->createSerializer(new Options([
            'class_serializers' => [
                $objectClass => static function ($object): array {
                    return [
                        'purpose' => $object->getPurpose(),
                    ];
                },
            ],
        ]));

        $this->assertEquals([
            'class' => $objectClass,
            'data' => [
                'purpose' => 'To be tested!',
            ],
        ], $this->invokeSerialization($serializer, $object));
    }

    public function testSerializableObject(): void
    {
        $serializer = $this->createSerializer();

        $serializedValue = [
            'testing' => 'value',
        ];

        $object = $this->createMock(SerializableInterface::class);
        $object->method('toSentry')
            ->willReturn($serializedValue);

        $this->assertEquals([
            'class' => \get_class($object),
            'data' => $serializedValue,
        ], $this->invokeSerialization($serializer, $object));
    }

    public function testSerializableThatReturnsNull(): void
    {
        $serializer = $this->createSerializer();

        $object = $this->createMock(SerializableInterface::class);
        $object->method('toSentry')
            ->willReturn(null);

        $this->assertEquals('Object ' . \get_class($object), $this->invokeSerialization($serializer, $object));
    }

    public function testSerializableObjectThatThrowsAnException(): void
    {
        $serializer = $this->createSerializer();

        $object = $this->createMock(SerializableInterface::class);
        $object->method('toSentry')
            ->willThrowException(new \Exception('Doesn\'t matter what the exception is.'));

        $this->assertEquals('Object ' . \get_class($object), $this->invokeSerialization($serializer, $object));
    }

    public function testLongStringWithOverwrittenMessageLength(): void
    {
        $serializer = $this->createSerializer(new Options(['max_value_length' => 500]));

        foreach ([100, 490, 499, 500, 501, 1000, 10000] as $length) {
            $input = str_repeat('x', $length);
            $result = $this->invokeSerialization($serializer, $input);

            $this->assertInternalType('string', $result);
            $this->assertLessThanOrEqual(500, \strlen($result));
        }
    }

    public function testClippingUTF8Characters(): void
    {
        $serializer = $this->createSerializer(new Options(['max_value_length' => 19]));

        $clipped = $this->invokeSerialization($serializer, 'Прекратите надеяться, что ваши пользователи будут сообщать об ошибках');

        $this->assertSame('Прекратит {clipped}', $clipped);
        $this->assertNotNull(json_encode($clipped));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * @return Serializer
     */
    protected function createSerializer(?Options $options = null): AbstractSerializer
    {
        return new Serializer($options ?? new Options());
    }
}
