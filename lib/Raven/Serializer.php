<?php
/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

/**
 * This helper is based on code from Facebook's Phabricator project
 *
 *   https://github.com/facebook/phabricator
 *
 * Specifically, it is an adaptation of the PhutilReadableSerializer class.
 *
 * @package raven
 */
class Raven_Serializer
{
    /**
     * Serialize an object (recursively) into something safe for data
     * sanitization and encoding.
     */
    public function serialize($value, $max_depth=4, $_depth=0)
    {
        if (is_object($value) || is_resource($value)) {
            return $this->serializeValue($value);
        } elseif ($_depth < $max_depth && is_array($value)) {
            $new = array();
            foreach ($value as $k => $v) {
                $new[$this->serializeValue($k)] = $this->serialize($v, $max_depth, $_depth + 1);
            }

            return $new;
        } else {
            return $this->serializeValue($value);
        }
    }

    protected function serializeValue($value)
    {
        if (is_null($value) || is_bool($value) || is_float($value) || is_integer($value)) {
            return $value;
        } elseif (is_object($value) || gettype($value) == 'object') {
            return 'Object '.get_class($value);
        } elseif (is_resource($value)) {
            return 'Resource '.get_resource_type($value);
        } elseif (is_array($value)) {
            return 'Array of length ' . count($value);
        } else {
            $value = (string) $value;

            $list  = 'UTF-8, ASCII, ISO-8859-1, ISO-8859-2, ISO-8859-3, ISO-8859-4, ISO-8859-5, ISO-8859-6, ';
            $list .= 'ISO-8859-7, ISO-8859-8, ISO-8859-9, ISO-8859-10, ISO-8859-13, ISO-8859-14, ISO-8859-15, ';
            $list .= 'ISO-8859-16, Windows-1251, Windows-1252, Windows-1254';

            if (function_exists('mb_detect_encoding')
                && function_exists('mb_convert_encoding')
                && $currentEncoding = mb_detect_encoding($value, $list)
            ) {
                $value = mb_convert_encoding($value, 'UTF-8', $currentEncoding);
            }

            return $value;
        }
    }
}
