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

        $this->assertIsInt($result);
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

        $this->assertIsFloat($result);
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
        $object = new class {
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

    /**
     * @dataProvider serializeDateTimeDataProvider
     */
    public function testSerializeDateTime(\DateTimeInterface $date, string $expected): void
    {
        $serializer = $this->createSerializer();

        $result = $this->invokeSerialization($serializer, $date);

        $this->assertSame($expected, $result);
    }

    public function serializeDateTimeDataProvider(): \Generator
    {
        yield 'DateTime' => [
            new \DateTime('2001-02-03 13:37:42'),
            'DateTime(2001-02-03 13:37:42)',
        ];

        yield 'Microseconds' => [
            new \DateTimeImmutable('2001-02-03 13:37:42.123456'),
            'DateTimeImmutable(2001-02-03 13:37:42.123456)',
        ];

        yield 'Timezone' => [
            new \DateTime('2001-02-03 13:37:42', new \DateTimeZone('Europe/Paris')),
            'DateTime(2001-02-03 13:37:42 Europe/Paris+01:00)',
        ];

        yield 'Abbreviated timezone' => [
            new \DateTime('2021-03-28 13:37:42 CET'),
            'DateTime(2021-03-28 13:37:42 CET+01:00)',
        ];
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

    /**
     * @return Serializer
     */
    protected function createSerializer(?Options $options = null): AbstractSerializer
    {
        return new Serializer($options ?? new Options());
    }
}
