<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\Serializer;

final class SerializerTest extends AbstractSerializerTest
{
    protected function getSerializerUnderTest()
    {
        return new Serializer();
    }
}
