<?php

declare(strict_types=1);

namespace Sentry\Tests\Util\Fixtures;

class JsonSerializableClass implements \JsonSerializable
{
    public $keyPublic = 'public';

    public function jsonSerialize(): array
    {
        return [
            'key' => 'value',
        ];
    }
}
