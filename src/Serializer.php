<?php

declare(strict_types=1);
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

namespace Sentry;

/**
 * This helper is based on code from Facebook's Phabricator project.
 *
 *   https://github.com/facebook/phabricator
 *
 * Specifically, it is an adaptation of the PhutilReadableSerializer class.
 */
class Serializer
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
     * @var string[]|string
     */
    protected $mb_detect_order = self::DEFAULT_MB_DETECT_ORDER;

    /**
     * @var bool
     */
    protected $_all_object_serialize = false;

    /**
     * The default maximum message lengths. Longer strings will be truncated.
     *
     * @var int
     */
    protected $messageLimit;

    /**
     * @param null|string|string[] $mb_detect_order
     * @param null|int             $messageLimit
     */
    public function __construct($mb_detect_order = null, $messageLimit = Client::MESSAGE_MAX_LENGTH_LIMIT)
    {
        if (null != $mb_detect_order) {
            $this->mb_detect_order = $mb_detect_order;
        }

        $this->messageLimit = (int) $messageLimit;
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
    public function serialize($value, $max_depth = 3, $_depth = 0)
    {
        if ($_depth < $max_depth) {
            if (\is_array($value)) {
                $new = [];
                foreach ($value as $k => $v) {
                    $new[$this->serializeValue($k)] = $this->serialize($v, $max_depth, $_depth + 1);
                }

                return $new;
            }

            if (\is_object($value)) {
                if (\is_callable($value)) {
                    return $this->serializeCallable($value);
                }

                if ($this->_all_object_serialize || ('stdClass' === \get_class($value))) {
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
     * @return array|string|bool|float|int|null
     */
    public function serializeObject($object, $max_depth = 3, $_depth = 0, $hashes = [])
    {
        if (($_depth >= $max_depth) or \in_array(spl_object_hash($object), $hashes)) {
            return $this->serializeValue($object);
        }
        $hashes[] = spl_object_hash($object);
        $return = [];
        foreach ($object as $key => &$value) {
            if (\is_object($value)) {
                $new_value = $this->serializeObject($value, $max_depth, $_depth + 1, $hashes);
            } else {
                $new_value = $this->serialize($value, $max_depth, $_depth + 1);
            }
            $return[$key] = $new_value;
        }

        return $return;
    }

    protected function serializeString($value)
    {
        $value = (string) $value;

        if (\extension_loaded('mbstring')) {
            // we always guarantee this is coerced, even if we can't detect encoding
            if ($currentEncoding = mb_detect_encoding($value, $this->mb_detect_order)) {
                $value = mb_convert_encoding($value, 'UTF-8', $currentEncoding);
            } else {
                $value = mb_convert_encoding($value, 'UTF-8');
            }

            if (mb_strlen($value) > $this->messageLimit) {
                $value = mb_substr($value, 0, $this->messageLimit - 10, 'UTF-8') . ' {clipped}';
            }
        } else {
            if (\strlen($value) > $this->messageLimit) {
                $value = substr($value, 0, $this->messageLimit - 10) . ' {clipped}';
            }
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
        if ((null === $value) || \is_bool($value) || \is_float($value) || \is_int($value)) {
            return $value;
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

    /**
     * @param callable $callable
     *
     * @return string
     */
    public function serializeCallable(callable $callable)
    {
        if (\is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
            $class = $reflection->getDeclaringClass();
        } elseif ($callable instanceof \Closure || \is_string($callable)) {
            $reflection = new \ReflectionFunction($callable);
            $class = null;
        } else {
            throw new \InvalidArgumentException('Unrecognized type of callable');
        }

        $value = $reflection->isClosure() ? 'Lambda ' : 'Callable ';

        if ($reflection->getReturnType()) {
            $value .= $reflection->getReturnType() . ' ';
        }

        if ($class) {
            $value .= $class->getName() . '::';
        }

        return $value . $reflection->getName() . ' ' . $this->serializeCallableParameters($reflection);
    }

    /**
     * @param \ReflectionFunctionAbstract $reflection
     *
     * @return string
     */
    private function serializeCallableParameters(\ReflectionFunctionAbstract $reflection)
    {
        $params = [];
        foreach ($reflection->getParameters() as &$param) {
            $paramType = null;
            if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
                $paramType = $param->hasType() ? $param->getType() : 'mixed';
            } else {
                if ($param->isArray()) {
                    $paramType = 'array';
                } elseif ($param->isCallable()) {
                    $paramType = 'callable';
                }
            }
            if ($paramType && $param->allowsNull()) {
                $paramType .= '|null';
            }

            $paramName = ($param->isPassedByReference() ? '&' : '') . $param->getName();
            if ($param->isOptional()) {
                $paramName = '[' . $paramName . ']';
            }

            if ($paramType) {
                $params[] = $paramType . ' ' . $paramName;
            } else {
                $params[] = $paramName;
            }
        }

        return '[' . implode('; ', $params) . ']';
    }

    /**
     * @return string|string[]
     * @codeCoverageIgnore
     */
    public function getMbDetectOrder()
    {
        return $this->mb_detect_order;
    }

    /**
     * @param string $mb_detect_order
     *
     * @return \Sentry\Serializer
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

    /**
     * @return int
     */
    public function getMessageLimit()
    {
        return $this->messageLimit;
    }

    /**
     * @param int $messageLimit
     */
    public function setMessageLimit($messageLimit)
    {
        $this->messageLimit = $messageLimit;
    }
}
