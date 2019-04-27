<?php

namespace Sentry\Tests\Fixtures\classes;

use Sentry\Serializer\Serializable;

class StubSerializableObjectThrowingException implements Serializable
{
    public function toSentry(): array
    {
        throw new \Exception('This should result in the serialized value being ignored.');
    }
}
