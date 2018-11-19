<?php

namespace Sentry\Tests\Serializer;

use Sentry\Serializer\AbstractSerializer;
use Sentry\Serializer\Serializer;

class SerializerTest extends AbstractSerializerTest
{
    protected function getSerializerUnderTest(): AbstractSerializer
    {
        return new Serializer();
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertInternalType('integer', $result);
        $this->assertSame(1, $result);
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertInternalType('double', $result);
        $this->assertSame(1.5, $result);
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertTrue($result);

        $input = false;
        $result = $this->invokeSerialization($serializer, $input);
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
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertNull($result);
    }
}
