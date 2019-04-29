<?php

namespace Sentry\Tests\Fixtures\classes;

use Sentry\Serializer\SerializableInterface;

class StubSerializableInterfaceObject implements SerializableInterface
{
    public function toSentry(): array
    {
        return [
            'purpose' => 'Being serialized!',
        ];
    }
}
