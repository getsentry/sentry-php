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

use Sentry\Options;

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
     * @var Options The Sentry options
     */
    protected $options;

    /**
     * AbstractSerializer constructor.
     *
     * @param Options $options The SDK configuration options
     */
    public function __construct(Options $options, int $maxDepth = 3, ?string $mbDetectOrder = null)
    {
        $this->maxDepth = $maxDepth;

        if ($mbDetectOrder != null) {
            $this->mbDetectOrder = $mbDetectOrder;
        }

        $this->options = $options;
    }

    /**
     * Serialize an object (recursively) into something safe for data
     * sanitization and encoding.
     *
     * @param mixed $value
     *
     * @return string|bool|float|int|mixed[]|null
     */
    protected function serializeRecursively($value, int $_depth = 0)
    {
        try {
            if ($_depth >= $this->maxDepth) {
                return $this->serializeValue($value);
            }

            try {
                if (@\is_callable($value)) {
                    return $this->serializeCallable($value);
                }
            } catch (\Throwable $exception) {
                // Do nothing on purpose
            }

            if (\is_array($value)) {
                $serializedArray = [];

                foreach ($value as $k => $v) {
                    $serializedArray[$k] = $this->serializeRecursively($v, $_depth + 1);
                }

                return $serializedArray;
            }

            if (\is_object($value)) {
                $classSerializers = $this->resolveClassSerializers($value);

                // Try each serializer until there is none left or the serializer returned data
                foreach ($classSerializers as $classSerializer) {
                    try {
                        $serializedObjectData = $classSerializer($value);

                        if (\is_array($serializedObjectData)) {
                            return [
                                'class' => \get_class($value),
                                'data' => $this->serializeRecursively($serializedObjectData, $_depth + 1),
                            ];
                        }
                    } catch (\Throwable $e) {
                        // Ignore any exceptions generated by a class serializer
                    }
                }

                if ($this->serializeAllObjects || ($value instanceof \stdClass)) {
                    return $this->serializeObject($value, $_depth);
                }
            }

            return $this->serializeValue($value);
        } catch (\Throwable $error) {
            if (\is_string($value)) {
                return $value . ' {serialization error}';
            }

            return '{serialization error}';
        }
    }

    /**
     * Find class serializers for a object.
     *
     * Registered serializers with the `class_serializers` option take precedence over
     * objects implementing the `SerializableInterface`.
     *
     * @param object $object
     *
     * @return array<int, callable>
     */
    protected function resolveClassSerializers($object): array
    {
        $serializers = [];

        foreach ($this->options->getClassSerializers() as $type => $serializer) {
            if ($object instanceof $type || is_callable($serializer)) {
                $serializers[] = $serializer;
            }
        }

        if ($object instanceof SerializableInterface) {
            $serializers[] = static function (SerializableInterface $object): ?array {
                return $object->toSentry();
            };
        }

        return $serializers;
    }

    /**
     * @param object   $object
     * @param string[] $hashes
     *
     * @return mixed[]|string|bool|float|int|null
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

    /**
     * Serializes the given value to a string.
     *
     * @param mixed $value The value to serialize
     */
    protected function serializeString($value): string
    {
        $value = (string) $value;

        // we always guarantee this is coerced, even if we can't detect encoding
        if ($currentEncoding = mb_detect_encoding($value, $this->mbDetectOrder)) {
            $value = mb_convert_encoding($value, 'UTF-8', $currentEncoding);
        } else {
            $value = mb_convert_encoding($value, 'UTF-8');
        }

        if (mb_strlen($value) > $this->options->getMaxValueLength()) {
            $value = mb_substr($value, 0, $this->options->getMaxValueLength() - 10, 'UTF-8') . ' {clipped}';
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
        if (($value === null) || \is_bool($value) || is_numeric($value)) {
            return $value;
        }

        if (\is_object($value)) {
            $reflection = new \ReflectionObject($value);

            $objectId = null;
            if ($reflection->hasProperty('id') && ($idProperty = $reflection->getProperty('id'))->isPublic()) {
                $objectId = $idProperty->getValue($value);
            } elseif ($reflection->hasMethod('getId') && ($getIdMethod = $reflection->getMethod('getId'))->isPublic()) {
                try {
                    $objectId = $getIdMethod->invoke($value);
                } catch (\Throwable $e) {
                    // Do nothing on purpose
                }
            }

            return 'Object ' . $reflection->getName() . (\is_scalar($objectId) ? '(#' . $objectId . ')' : '');
        }

        if (\is_resource($value)) {
            return 'Resource ' . get_resource_type($value);
        }

        try {
            if (@\is_callable($value)) {
                return $this->serializeCallable($value);
            }
        } catch (\Throwable $exception) {
            // Do nothing on purpose
        }

        if (\is_array($value)) {
            return 'Array of length ' . \count($value);
        }

        return $this->serializeString($value);
    }

    /**
     * @param callable|mixed $callable
     */
    protected function serializeCallable($callable): string
    {
        if (\is_string($callable) && !\function_exists($callable)) {
            return $callable;
        }

        if (!\is_callable($callable)) {
            throw new \InvalidArgumentException(sprintf('Expecting callable, got %s', \is_object($callable) ? \get_class($callable) : \gettype($callable)));
        }

        try {
            if (\is_array($callable)) {
                $reflection = new \ReflectionMethod($callable[0], $callable[1]);
                $class = $reflection->getDeclaringClass();
            } elseif ($callable instanceof \Closure || (\is_string($callable) && \function_exists($callable))) {
                $reflection = new \ReflectionFunction($callable);
                $class = null;
            } elseif (\is_object($callable) && method_exists($callable, '__invoke')) {
                $reflection = new \ReflectionMethod($callable, '__invoke');
                $class = $reflection->getDeclaringClass();
            } else {
                throw new \InvalidArgumentException('Unrecognized type of callable');
            }
        } catch (\ReflectionException $exception) {
            return '{unserializable callable, reflection error}';
        }

        $callableType = $reflection->isClosure() ? 'Lambda ' : 'Callable ';
        $callableReturnType = $reflection->getReturnType();

        if ($callableReturnType instanceof \ReflectionNamedType) {
            $callableType .= $callableReturnType->getName() . ' ';
        }

        if ($class) {
            $callableType .= $class->getName() . '::';
        }

        return $callableType . $reflection->getName() . ' ' . $this->serializeCallableParameters($reflection);
    }

    private function serializeCallableParameters(\ReflectionFunctionAbstract $reflection): string
    {
        $params = [];
        foreach ($reflection->getParameters() as &$param) {
            $reflectionType = $param->getType();
            if ($reflectionType instanceof \ReflectionNamedType) {
                $paramType = $reflectionType->getName();
            } else {
                $paramType = 'mixed';
            }

            if ($param->allowsNull()) {
                $paramType .= '|null';
            }

            $paramName = ($param->isPassedByReference() ? '&' : '') . $param->getName();

            if ($param->isOptional()) {
                $paramName = '[' . $paramName . ']';
            }

            $params[] = $paramType . ' ' . $paramName;
        }

        return '[' . implode('; ', $params) . ']';
    }

    public function getMbDetectOrder(): string
    {
        return $this->mbDetectOrder;
    }

    /**
     * @return $this
     */
    public function setMbDetectOrder(string $mbDetectOrder): self
    {
        $this->mbDetectOrder = $mbDetectOrder;

        return $this;
    }

    public function setSerializeAllObjects(bool $value): void
    {
        $this->serializeAllObjects = $value;
    }

    public function getSerializeAllObjects(): bool
    {
        return $this->serializeAllObjects;
    }
}
