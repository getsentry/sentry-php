<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Serializer;

/**
 * Serializes a value into a representation that should reasonably suggest
 * both the type and value, and be serializable into JSON.
 */
class RepresentationSerializer extends AbstractSerializer implements RepresentationSerializerInterface
{
    public function representationSerialize($value)
    {
        return $this->serializeRecursively($value);
    }

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
        } elseif (\is_numeric($value)) {
            return (string) $value;
        }

        return parent::serializeValue($value);
    }
}
