<?php

declare(strict_types=1);

namespace Sentry\Tests\Serializer;

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

    /**
     * @return Serializer
     */
    protected function createSerializer(): AbstractSerializer
    {
        return new Serializer();
    }
}
