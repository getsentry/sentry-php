<?php

declare(strict_types=1);

namespace Sentry;

interface SerializerInterface
{
    /**
     * Serialize an object (recursively) into something safe to be sent in an Event
     *
     * @param mixed $value

     * @return string|bool|float|int|null|array
     */
    public function serialize($value);
}
