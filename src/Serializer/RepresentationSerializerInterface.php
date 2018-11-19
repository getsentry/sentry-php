<?php

declare(strict_types=1);

namespace Sentry\Serializer;


interface RepresentationSerializerInterface
{
    /**
     * Serialize an object (recursively) into something safe to be sent as a stacktrace frame argument.
     * 
     * The main difference with the {@link Sentry\SerializerInterface} is the fact that even basic types
     * (bool, int, float) are converted into strings, to avoid misrepresentations on the server side. 
     *
     * @param mixed $value

     * @return string|bool|float|int|null|array
     */
    public function representationSerialize($value);
}
