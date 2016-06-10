<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Serializes a value into a representation that should reasonable suggest
 * both the type and value, and be serializable into JSON.
 * @package raven
 */
class Raven_ReprSerializer extends Raven_Serializer
{
    protected function serializeValue($value)
    {
        if ($value === null) {
            return 'null';
        } elseif ($value === false) {
            return 'false';
        } elseif ($value === true) {
            return 'true';
        } elseif (is_float($value) && (int) $value == $value) {
            return $value.'.0';
        } elseif (is_integer($value) || is_float($value)) {
            return (string) $value;
        } elseif (is_object($value) || gettype($value) == 'object') {
            return 'Object '.get_class($value);
        } elseif (is_resource($value)) {
            return 'Resource '.get_resource_type($value);
        } elseif (is_array($value)) {
            return 'Array of length ' . count($value);
        } else {
            $value = (string) $value;

            if (function_exists('mb_detect_encoding')
                && function_exists('mb_convert_encoding')
                && $currentEncoding = mb_detect_encoding($value, 'auto')
            ) {
                $value = mb_convert_encoding($value, 'UTF-8', $currentEncoding);
            }

            return $value;
        }
    }
}
