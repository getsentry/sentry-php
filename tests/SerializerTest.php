<?php

namespace Raven\Tests;

require_once 'SerializerAbstractTest.php';

class SerializerTest extends \Raven\Tests\SerializerAbstractTest
{
    /**
     * @return string
     */
    protected static function get_test_class()
    {
        return '\\Raven\\Serializer';
    }

    /**
     * @param boolean $serialize_all_objects
     * @dataProvider dataGetBaseParam
     * @covers \Raven\Serializer::serializeString
     */
    public function testBrokenEncoding($serialize_all_objects)
    {
        parent::testBrokenEncoding($serialize_all_objects);
    }

    /**
     * @param boolean $serialize_all_objects
     * @dataProvider dataGetBaseParam
     * @covers \Raven\Serializer::serializeString
     */
    public function testLongString($serialize_all_objects)
    {
        parent::testLongString($serialize_all_objects);
    }

    /**
     * @param boolean $serialize_all_objects
     * @dataProvider dataGetBaseParam
     * @covers \Raven\Serializer::serializeValue
     */
    public function testSerializeValueResource($serialize_all_objects)
    {
        parent::testSerializeValueResource($serialize_all_objects);
    }
}
