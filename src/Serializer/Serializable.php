<?php

namespace Sentry\Serializer;

interface Serializable
{
    /**
     * Return an array representation of the object for Sentry.
     *
     * @return array
     */
    public function __toSentry(): array;
}
