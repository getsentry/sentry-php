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

use Raven\ReprSerializer;

class ReprSerializerTest extends SerializerAbstractTest
{
    protected function getSerializerUnderTest()
    {
        return new ReprSerializer();
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testIntsAreInts($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = 1;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals(1, $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testFloats($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = 1.5;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals('1.5', $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testBooleans($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = true;
        $result = $serializer->serialize($input);
        $this->assertEquals('true', $result);

        $input = false;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals('false', $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     */
    public function testNull($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }
        $input = null;
        $result = $serializer->serialize($input);
        $this->assertInternalType('string', $result);
        $this->assertEquals('null', $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider dataGetBaseParam
     * @covers \Raven\ReprSerializer::serializeValue
     */
    public function testSerializeRoundedFloat($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setAllObjectSerialize(true);
        }

        $result = $serializer->serialize((float) 1);
        $this->assertInternalType('string', $result);
        $this->assertEquals('1.0', $result);

        $result = $serializer->serialize(floor(5 / 2));
        $this->assertInternalType('string', $result);
        $this->assertEquals('2.0', $result);

        $result = $serializer->serialize(floor(12345.678901234));
        $this->assertInternalType('string', $result);
        $this->assertEquals('12345.0', $result);
    }
}
