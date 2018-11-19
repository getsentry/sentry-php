<?php

namespace Sentry\Serializer;

class Serializer extends AbstractSerializer implements SerializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function serialize($value)
    {
        return $this->serializeRecursively($value);
    }
}
