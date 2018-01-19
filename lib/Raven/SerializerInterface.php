<?php


namespace Raven;

interface SerializerInterface
{
    /**
     * Serialize an object (recursively) into something safe for data
     * sanitization and encoding.
     *
     * @param mixed $value
     * @param array $context
     * @return string|bool|double|int|null|object|array
     */
    public function serialize($value, array $context = []);

    /**
     * @param bool $value
     */
    public function setAllObjectSerialize($value);
}
