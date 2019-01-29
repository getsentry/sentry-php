<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

use Sentry\Options;
use Sentry\Serializer\AbstractSerializer;
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

    public function testLongStringWithOverwrittenMessageLength(): void
    {
        $serializer = $this->createSerializer(new Options(['truncation_length' => 500]));

        foreach ([100, 490, 499, 500, 501, 1000, 10000] as $length) {
            $input = str_repeat('x', $length);
            $result = $this->invokeSerialization($serializer, $input);

            $this->assertInternalType('string', $result);
            $this->assertLessThanOrEqual(500, \strlen($result));
        }
    }

    public function testClippingUTF8Characters(): void
    {
        $serializer = $this->createSerializer(new Options(['truncation_length' => 19]));

        $clipped = $this->invokeSerialization($serializer, 'Прекратите надеяться, что ваши пользователи будут сообщать об ошибках');

        $this->assertSame('Прекратит {clipped}', $clipped);
        $this->assertNotNull(json_encode($clipped));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * @param Options $options|null
     *
     * @return Serializer
     */
    protected function createSerializer(?Options $options = null): AbstractSerializer
    {
        if (null === $options) {
            $options = new Options();
        }

        return new Serializer($options);
    }
}
