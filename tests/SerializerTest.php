<?php

namespace Sentry\Tests;

use Sentry\Serializer\Serializer;

class SerializerTest extends AbstractSerializerTest
{
    protected function getSerializerUnderTest()
    {
        return new Serializer();
    }
}
