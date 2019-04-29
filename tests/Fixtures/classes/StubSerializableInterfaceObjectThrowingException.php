<?php

namespace Sentry\Tests\Fixtures\classes;

use Sentry\Serializer\SerializableInterface;

class StubSerializableInterfaceObjectThrowingException implements SerializableInterface
{
    public function toSentry(): array
    {
        throw new \Exception('This should result in the serialized value being ignored.');
    }
}
