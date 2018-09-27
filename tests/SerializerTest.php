<?php

namespace Sentry\Tests;

use Sentry\Serializer;

class SerializerTest extends AbstractSerializerTest
{
    protected function getSerializerUnderTest()
    {
        return new Serializer();
    }
}
