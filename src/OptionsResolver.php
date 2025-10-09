<?php

declare(strict_types=1);

namespace Sentry;

use Psr\Log\LoggerInterface;

class OptionsResolver
{
    /**
     * Contains all default values and also acts as a kind of schema.
     * Only values present in the defaults can be overwritten.
     *
     * @var array<string, mixed>
     */
    private $defaults = [];

    /**
     * List of allowed types for each top level array key.
     *
     * @var array<string, string[]>
     */
    private $allowedTypes = [];

    /**
     * List of valid values or a validation callback for each top level array key.
     *
     * @var array<string, mixed[]|callable>
     */
    private $allowedValues = [];

    /**
     * Stores normalizers for each top level array key. Normalizers are executed for defaults and user provided options.
     *
     * @var array<callable>
     */
    private $normalizers = [];

    /**
     * @param array<string, mixed> $defaults
     */
    public function setDefaults(array $defaults): void
    {
        $processed = [];

        foreach ($defaults as $option => $defaultValue) {
            [$isValid, $normalized] = $this->normalizeAndValidate($option, $defaultValue);

            if (!$isValid) {
                // Since defaults are used as fallback values if passed options are invalid, we want to
                // get hard errors here to make sure we have something to fall back to.
                throw new \InvalidArgumentException(\sprintf('Invalid default for option "%s"', $option));
            }

            $processed[$option] = $normalized;
        }

        $this->defaults = $processed;
    }

    /**
     * @param mixed $value
     */
    public function setDefault(string $name, $value): void
    {
        [$isValid, $normalized] = $this->normalizeAndValidate($name, $value);

        if (!$isValid) {
            // Since defaults are used as fallback values if passed options are invalid, we want to
            // get hard errors here to make sure we have something to fall back to.
            throw new \InvalidArgumentException(\sprintf('Invalid default for option "%s"', $name));
        }

        $this->defaults[$name] = $normalized;
    }

    /**
     * @param mixed $types
     */
    public function setAllowedTypes(string $name, $types): void
    {
        $this->allowedTypes[$name] = \is_array($types) ? $types : [$types];
    }

    /**
     * @param mixed[]|callable $values
     */
    public function setAllowedValues(string $path, $values): void
    {
        $this->allowedValues[$path] = $values;
    }

    public function setNormalizer(string $path, callable $normalizer): void
    {
        $this->normalizers[$path] = $normalizer;
    }

    /**
     * Resolves the passed options against the configured defaults.
     * If a value does not have a default value, it will be ignored.
     * If a value is invalid but has a default, it will fall back to using the default value.
     *
     * If a value doesn't exist or is invalid, a DEBUG log is generated using the configured logger.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function resolve(array $options = [], ?LoggerInterface $logger = null): array
    {
        return array_merge($this->defaults, $this->resolveOnly($options, $logger));
    }

    /**
     * Resolves passed options against the defaults but in contrast to {@see self::resolve}, it will not merge
     * defaults into the result. This means that the returning array will always be equal or smaller than
     * the input array.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function resolveOnly(array $options = [], ?LoggerInterface $logger = null): array
    {
        $result = [];

        foreach ($options as $option => $value) {
            if (!\array_key_exists($option, $this->defaults)) {
                if ($logger !== null) {
                    $logger->debug(\sprintf('Option "%s" does not exist and will be ignored', $option));
                }
                continue;
            }

            [$isValid, $normalized] = $this->normalizeAndValidate($option, $value);
            if (!$isValid) {
                if ($logger !== null) {
                    $logger->debug(\sprintf('Invalid value for option "%s". Using default value.', $option));
                }
                $result[$option] = $this->defaults[$option];
                continue;
            }

            $result[$option] = $normalized;
        }

        return $result;
    }

    /**
     * Normalizes and validates a value for a given path.
     *
     * @param mixed $value
     *
     * @return array{0: bool, 1: mixed} [isValid, normalizedValue]
     */
    private function normalizeAndValidate(string $name, $value): array
    {
        if (!$this->validateType($name, $value)) {
            return [false, $value];
        }

        if (!$this->validateValue($name, $value)) {
            return [false, $value];
        }

        // If there's no normalizer for this path, or normalization is a no-op, skip re-validation
        $normalizer = $this->normalizers[$name] ?? null;
        if ($normalizer === null) {
            return [true, $value];
        }

        // Normalize, then validate again only if the value actually changed
        $normalized = $normalizer($value);
        if ($normalized === $value) {
            return [true, $value];
        }

        if (!$this->validateType($name, $normalized)) {
            return [false, $normalized];
        }

        if (!$this->validateValue($name, $normalized)) {
            return [false, $normalized];
        }

        return [true, $normalized];
    }

    /**
     * @param mixed $value
     */
    private function validateType(string $name, $value): bool
    {
        $allowedTypes = $this->allowedTypes[$name] ?? null;
        if ($allowedTypes === null) {
            return true;
        }

        foreach ($allowedTypes as $typeSpec) {
            if ($this->valueMatchesType($value, $typeSpec)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether a value matches a given type specification.
     * Supports built-ins, FQCNs/interfaces and typed arrays like "string[]" or Foo\Bar\Baz[].
     *
     * @param mixed $value
     */
    private function valueMatchesType($value, string $typeSpec): bool
    {
        if (substr($typeSpec, -2) === '[]') {
            $elementType = substr($typeSpec, 0, -2);

            if (!\is_array($value)) {
                return false;
            }

            foreach ($value as $element) {
                if (!$this->valueMatchesType($element, $elementType)) {
                    return false;
                }
            }

            return true;
        }

        switch ($typeSpec) {
            case 'string':
                return \is_string($value);
            case 'int':
            case 'integer':
                return \is_int($value);
            case 'float':
            case 'double':
                return \is_float($value);
            case 'boolean':
            case 'bool':
                return \is_bool($value);
            case 'array':
                return \is_array($value);
            case 'object':
                return \is_object($value);
            case 'callable':
                return \is_callable($value);
            case 'null':
                return $value === null;
        }

        if (\is_object($value)) {
            return $value instanceof $typeSpec;
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    private function validateValue(string $name, $value): bool
    {
        $allowedValue = $this->allowedValues[$name] ?? null;
        if ($allowedValue === null) {
            return true;
        }
        if (\is_callable($allowedValue)) {
            return $allowedValue($value);
        }
        if (\is_array($allowedValue)) {
            return \in_array($value, $allowedValue, true);
        }

        return false;
    }
}
