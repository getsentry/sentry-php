<?php

namespace Sentry\Tests\Fixtures\classes;

use Sentry\Serializer\Serializable;

class StubSerializableObject implements Serializable
{
    public function toSentry(): array
    {
        return [
            'purpose' => 'Being serialized!',
        ];
    }
}
