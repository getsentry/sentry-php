<?php

declare(strict_types=1);

namespace Sentry;

/**
 * Serializes a value into a representation that should reasonably suggest
 * both the type and value, and be serializable into JSON.
 */
class ReprSerializer extends \Sentry\Serializer
{
    protected function serializeValue($value)
    {
        if (null === $value) {
            return 'null';
        } elseif (false === $value) {
            return 'false';
        } elseif (true === $value) {
            return 'true';
        } elseif (\is_float($value) && (int) $value == $value) {
            return $value . '.0';
        } elseif (\is_int($value) || \is_float($value)) {
            return (string) $value;
        } elseif (\is_object($value) || 'object' == \gettype($value)) {
            return 'Object ' . \get_class($value);
        } elseif (\is_resource($value)) {
            return 'Resource ' . get_resource_type($value);
        } elseif (\is_array($value)) {
            return 'Array of length ' . \count($value);
        } else {
            return $this->serializeString($value);
        }
    }
}
