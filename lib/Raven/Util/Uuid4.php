<?php

namespace Raven\Util;

use Ramsey\Uuid\Uuid;

class Uuid4
{
    /**
     * @return string
     */
    public static function generate()
    {
        $uuid = Uuid::uuid4()->toString();

        return str_replace('-', '', $uuid);
    }
}
