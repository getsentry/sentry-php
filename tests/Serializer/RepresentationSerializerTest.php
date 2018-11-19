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

use Sentry\Serializer\AbstractSerializer;
use Sentry\Serializer\RepresentationSerializer;

class RepresentationSerializerTest extends AbstractSerializerTest
{
    /**
     * @return RepresentationSerializer
     */
    protected function getSerializerUnderTest(): AbstractSerializer
    {
        return new RepresentationSerializer();
    }

    /**
     * @param bool $serializeAllObjects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testArraysAreArrays($serializeAllObjects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serializeAllObjects) {
            $serializer->setSerializeAllObjects(true);
        }
        $input = [1, 2, 3];
        $result = $this->invokeSerialization($serializer, $input);
        $this->assertSame(['1', '2', '3'], $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testIntsBecomeStrings($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setSerializeAllObjects(true);
        }
        $input = 1;
        $result = $serializer->representationSerialize($input);
        $this->assertInternalType('string', $result);
        $this->assertSame('1', $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testFloatsBecomeStrings($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setSerializeAllObjects(true);
        }
        $input = 1.5;
        $result = $serializer->representationSerialize($input);
        $this->assertInternalType('string', $result);
        $this->assertSame('1.5', $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testBooleansBecomeStrings($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setSerializeAllObjects(true);
        }
        $input = true;
        $result = $serializer->representationSerialize($input);
        $this->assertSame('true', $result);

        $input = false;
        $result = $serializer->representationSerialize($input);
        $this->assertInternalType('string', $result);
        $this->assertSame('false', $result);
    }

    /**
     * @param bool $serialize_all_objects
     * @dataProvider serializeAllObjectsProvider
     */
    public function testNullsBecomeString($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setSerializeAllObjects(true);
        }
        $input = null;
        $result = $serializer->representationSerialize($input);
        $this->assertInternalType('string', $result);
        $this->assertSame('null', $result);
    }

    /**
     * @dataProvider serializeAllObjectsProvider
     * @covers \Sentry\Serializer\RepresentationSerializer::serializeValue
     */
    public function testSerializeRoundedFloat($serialize_all_objects)
    {
        $serializer = $this->getSerializerUnderTest();
        if ($serialize_all_objects) {
            $serializer->setSerializeAllObjects(true);
        }

        $result = $serializer->representationSerialize((float) 1);
        $this->assertInternalType('string', $result);
        $this->assertSame('1.0', $result);

        $result = $serializer->representationSerialize(floor(5 / 2));
        $this->assertInternalType('string', $result);
        $this->assertSame('2.0', $result);

        $result = $serializer->representationSerialize(floor(12345.678901234));
        $this->assertInternalType('string', $result);
        $this->assertSame('12345.0', $result);
    }
}
