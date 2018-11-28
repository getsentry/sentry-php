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

namespace Sentry\Serializer;

use Sentry\Client;

/**
 * This helper is based on code from Facebook's Phabricator project.
 *
 *   https://github.com/facebook/phabricator
 *
 * Specifically, it is an adaptation of the PhutilReadableSerializer class.
 */
abstract class AbstractSerializer
{
    /**
     * The default mb detect order.
     *
     * @see http://php.net/manual/en/function.mb-detect-encoding.php
     */
    public const DEFAULT_MB_DETECT_ORDER = 'auto';

    /**
     * Suggested detect order for western countries.
     */
    public const WESTERN_MB_DETECT_ORDER = 'UTF-8, ASCII, ISO-8859-1, ISO-8859-2, ISO-8859-3, ISO-8859-4, ISO-8859-5, ISO-8859-6, ISO-8859-7, ISO-8859-8, ISO-8859-9, ISO-8859-10, ISO-8859-13, ISO-8859-14, ISO-8859-15, ISO-8859-16, Windows-1251, Windows-1252, Windows-1254';

    /**
     * The maximum depth to reach when serializing recursively.
     *
     * @var int
     */
    private $maxDepth;

    /**
     * This is the default mb detect order for the detection of encoding.
     *
     * @var string
     */
    protected $mbDetectOrder = self::DEFAULT_MB_DETECT_ORDER;

    /**
     * @var bool Flag to enable serialization of objects internal properties
     */
    protected $serializeAllObjects = false;

    /**
     * The default maximum message lengths. Longer strings will be truncated.
     *
     * @var int
     */
    protected $messageLimit;

    /**
     * Whether the ext-mbstring PHP extension is enabled or not.
     *
     * @var bool
     */
    private $mbStringEnabled;

    public function __construct(int $maxDepth = 3, ?string $mbDetectOrder = null, int $messageLimit = Client::MESSAGE_MAX_LENGTH_LIMIT)
    {
        $this->maxDepth = $maxDepth;

        if (null != $mbDetectOrder) {
            $this->mbDetectOrder = $mbDetectOrder;
        }

        $this->messageLimit = $messageLimit;
    }

    /**
     * Serialize an object (recursively) into something safe for data
     * sanitization and encoding.
     *
     * @param mixed $value
     * @param int   $_depth
     *
     * @return string|bool|float|int|null|object|array
     */
    protected function serializeRecursively($value, int $_depth = 0)
    {
        if ($_depth >= $this->maxDepth) {
            return $this->serializeValue($value);
        }

        if (\is_callable($value)) {
            return $this->serializeCallable($value);
        }

        if (\is_array($value)) {
            $serializedArray = [];

            foreach ($value as $k => $v) {
                $serializedArray[$k] = $this->serializeRecursively($v, $_depth + 1);
            }

            return $serializedArray;
        }

        if (\is_object($value)) {
            if ($this->serializeAllObjects || ('stdClass' === \get_class($value))) {
                return $this->serializeObject($value, $_depth, []);
            }
        }

        return $this->serializeValue($value);
    }

    /**
     * @param object   $object
     * @param int      $_depth
     * @param string[] $hashes
     *
     * @return array|string|bool|float|int|null
     */
    protected function serializeObject($object, int $_depth = 0, array $hashes = [])
    {
        if ($_depth >= $this->maxDepth || \in_array(spl_object_hash($object), $hashes, true)) {
            return $this->serializeValue($object);
        }

        $hashes[] = spl_object_hash($object);
        $serializedObject = [];

        foreach ($object as $key => &$value) {
            if (\is_object($value)) {
                $serializedObject[$key] = $this->serializeObject($value, $_depth + 1, $hashes);
            } else {
                $serializedObject[$key] = $this->serializeRecursively($value, $_depth + 1);
            }
        }

        return $serializedObject;
    }

    protected function serializeString($value)
    {
        $value = (string) $value;

        if ($this->isMbStringEnabled()) {
            // we always guarantee this is coerced, even if we can't detect encoding
            if ($currentEncoding = \mb_detect_encoding($value, $this->mbDetectOrder)) {
                $value = \mb_convert_encoding($value, 'UTF-8', $currentEncoding);
            } else {
                $value = \mb_convert_encoding($value, 'UTF-8');
            }

            if (\mb_strlen($value) > $this->messageLimit) {
                $value = \mb_substr($value, 0, $this->messageLimit - 10, 'UTF-8') . ' {clipped}';
            }
        } else {
            if (\strlen($value) > $this->messageLimit) {
                $value = \substr($value, 0, $this->messageLimit - 10) . ' {clipped}';
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
        if ((null === $value) || \is_bool($value) || \is_numeric($value)) {
            return $value;
        }

        if (\is_object($value)) {
            return 'Object ' . \get_class($value);
        }

        if (\is_resource($value)) {
            return 'Resource ' . get_resource_type($value);
        }

        if (\is_callable($value)) {
            return $this->serializeCallable($value);
        }

        if (\is_array($value)) {
            return 'Array of length ' . \count($value);
        }

        return $this->serializeString($value);
    }

    /**
     * @param callable $callable
     *
     * @return string
     */
    protected function serializeCallable(callable $callable): string
    {
        try {
            if (\is_array($callable)) {
                $reflection = new \ReflectionMethod($callable[0], $callable[1]);
                $class = $reflection->getDeclaringClass();
            } elseif ($callable instanceof \Closure || \is_string($callable)) {
                $reflection = new \ReflectionFunction($callable);
                $class = null;
            } elseif (\is_object($callable) && \method_exists($callable, '__invoke')) {
                $reflection = new \ReflectionMethod($callable, '__invoke');
                $class = $reflection->getDeclaringClass();
            } else {
                throw new \InvalidArgumentException('Unrecognized type of callable');
            }
        } catch (\ReflectionException $exception) {
            return '{unserializable callable, reflection error}';
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
    private function serializeCallableParameters(\ReflectionFunctionAbstract $reflection): string
    {
        $params = [];
        foreach ($reflection->getParameters() as &$param) {
            $paramType = $param->hasType() ? $param->getType() : 'mixed';

            if ($param->allowsNull()) {
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

    public function getMbDetectOrder(): string
    {
        return $this->mbDetectOrder;
    }

    /**
     * @param string $mbDetectOrder
     *
     * @return $this
     */
    public function setMbDetectOrder(string $mbDetectOrder): self
    {
        $this->mbDetectOrder = $mbDetectOrder;

        return $this;
    }

    /**
     * @param bool $value
     */
    public function setSerializeAllObjects(bool $value): void
    {
        $this->serializeAllObjects = $value;
    }

    /**
     * @return bool
     */
    public function getSerializeAllObjects(): bool
    {
        return $this->serializeAllObjects;
    }

    /**
     * @return int
     */
    public function getMessageLimit(): int
    {
        return $this->messageLimit;
    }

    /**
     * @param int $messageLimit
     *
     * @return AbstractSerializer
     */
    public function setMessageLimit(int $messageLimit): self
    {
        $this->messageLimit = $messageLimit;

        return $this;
    }

    private function isMbStringEnabled(): bool
    {
        if (null === $this->mbStringEnabled) {
            $this->mbStringEnabled = \extension_loaded('mbstring');
        }

        return $this->mbStringEnabled;
    }
}
