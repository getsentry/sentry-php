<?php

namespace Raven\Tests;

use Raven\Serializer;

class SerializerTest extends SerializerAbstractTest
{
    protected function getSerializerUnderTest()
    {
        return new Serializer();
    }
}
