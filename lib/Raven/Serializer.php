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

namespace Raven;

/**
 * This helper is based on code from Facebook's Phabricator project.
 *
 *   https://github.com/facebook/phabricator
 *
 * Specifically, it is an adaptation of the PhutilReadableSerializer class.
 */
class Serializer implements SerializerInterface
{
    /*
     * The default mb detect order
     *
     * @see http://php.net/manual/en/function.mb-detect-encoding.php
     */
    const DEFAULT_MB_DETECT_ORDER = 'auto';

    /*
     * Suggested detect order for western countries
     */
    const WESTERN_MB_DETECT_ORDER = 'UTF-8, ASCII, ISO-8859-1, ISO-8859-2, ISO-8859-3, ISO-8859-4, ISO-8859-5, ISO-8859-6, ISO-8859-7, ISO-8859-8, ISO-8859-9, ISO-8859-10, ISO-8859-13, ISO-8859-14, ISO-8859-15, ISO-8859-16, Windows-1251, Windows-1252, Windows-1254';

    /**
     * This is the default mb detect order for the detection of encoding.
     *
     * @var string
     */
    protected $mb_detect_order = self::DEFAULT_MB_DETECT_ORDER;

    /**
     * @var bool
     */
    protected $_all_object_serialize = false;

    /**
     * @param null|string $mb_detect_order
     */
    public function __construct($mb_detect_order = null)
    {
        if (null != $mb_detect_order) {
            $this->mb_detect_order = $mb_detect_order;
        }
    }

    /**
     * Serialize an object (recursively) into something safe for data
     * sanitization and encoding.
     *
     * @param mixed $value
     * @param array $context
     *
     * @return string|bool|float|int|null|object|array
     */
    public function serialize($value, array $context = [])
    {
        $full_context = $this->getFullContext($context);

        return $this->serializeInner($value, $full_context['max_depth'], 0);
    }

    /**
     * @param array $context
     *
     * @return array
     */
    protected function getFullContext(array $context)
    {
        if (!array_key_exists('max_depth', $context)) {
            $context['max_depth'] = 3;
        }

        return $context;
    }

    /**
     * Serialize an object (recursively) into something safe for data
     * sanitization and encoding.
     *
     * @param mixed $value
     * @param int   $max_depth
     * @param int   $_depth
     *
     * @return string|bool|float|int|null|object|array
     */
    protected function serializeInner($value, $max_depth, $_depth)
    {
        if ($_depth < $max_depth) {
            if (is_array($value)) {
                $new = [];
                foreach ($value as $k => $v) {
                    $new[$this->serializeValue($k)] = $this->serializeInner($v, $max_depth, $_depth + 1);
                }

                return $new;
            }

            if (is_object($value)) {
                if (('stdClass' == get_class($value)) or $this->_all_object_serialize) {
                    return $this->serializeObject($value, $max_depth, $_depth, []);
                }
            }
        }

        return $this->serializeValue($value);
    }

    /**
     * @param object   $object
     * @param int      $max_depth
     * @param int      $_depth
     * @param string[] $hashes
     *
     * @return array|string
     */
    public function serializeObject($object, $max_depth = 3, $_depth = 0, $hashes = [])
    {
        if (($_depth >= $max_depth) or in_array(spl_object_hash($object), $hashes)) {
            return $this->serializeValue($object);
        }
        $hashes[] = spl_object_hash($object);
        $return = [];
        foreach ($object as $key => &$value) {
            if (is_object($value)) {
                $new_value = $this->serializeObject($value, $max_depth, $_depth + 1, $hashes);
            } else {
                $new_value = $this->serialize($value, ['max_depth' => $max_depth - $_depth - 1]);
            }
            $return[$key] = $new_value;
        }

        return $return;
    }

    protected function serializeString($value)
    {
        $value = (string) $value;
        if (function_exists('mb_detect_encoding')
            && function_exists('mb_convert_encoding')
        ) {
            // we always guarantee this is coerced, even if we can't detect encoding
            if ($currentEncoding = mb_detect_encoding($value, $this->mb_detect_order)) {
                $value = mb_convert_encoding($value, 'UTF-8', $currentEncoding);
            } else {
                $value = mb_convert_encoding($value, 'UTF-8');
            }
        }

        if (strlen($value) > 1024) {
            $value = substr($value, 0, 1014) . ' {clipped}';
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return string|bool|float|int|null
     */
    protected function serializeValue($value)
    {
        if ((null === $value) || is_bool($value) || is_float($value) || is_int($value)) {
            return $value;
        } elseif (is_object($value) || 'object' == gettype($value)) {
            return 'Object ' . get_class($value);
        } elseif (is_resource($value)) {
            return 'Resource ' . get_resource_type($value);
        } elseif (is_array($value)) {
            return 'Array of length ' . count($value);
        } else {
            return $this->serializeString($value);
        }
    }

    /**
     * @return string
     * @codeCoverageIgnore
     */
    public function getMbDetectOrder()
    {
        return $this->mb_detect_order;
    }

    /**
     * @param string $mb_detect_order
     *
     * @return \Raven\Serializer
     * @codeCoverageIgnore
     */
    public function setMbDetectOrder($mb_detect_order)
    {
        $this->mb_detect_order = $mb_detect_order;

        return $this;
    }

    /**
     * @param bool $value
     */
    public function setAllObjectSerialize($value)
    {
        $this->_all_object_serialize = $value;
    }

    /**
     * @return bool
     */
    public function getAllObjectSerialize()
    {
        return $this->_all_object_serialize;
    }
}
