<?php

namespace Raven\Tests;

use Raven\Serializer;

class SerializerTest extends AbstractSerializerTest
{
    protected function getSerializerUnderTest()
    {
        return new Serializer();
    }
}
