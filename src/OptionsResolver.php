<?php

declare(strict_types=1);

namespace Sentry;

use Psr\Log\LoggerInterface;
use Sentry\Util\Arr;

/**
 * A container that declares defaults and allows validation and normalization in a central place.
 * When a value fails validation, it will keep the current value (or fall back to the default value if there is no
 * current value) and emit debug logs.
 *
 * Supports a nested config with arbitrary number of layers.
 *
 * To set validation on nested values, it's possible to use a . (dot) syntax, similar to how JSON can be traversed.
 * For example, using 'foo.bar' will refer to
 * 'foo' => [
 *     'bar' => 'test'
 * ]
 *
 * Dot syntax is generally available to set validators and normalizers, while defaults are specified using
 * the real array shape
 *
 * @internal
 */
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
     * List of all allowed types for a path. Stored using dot syntax.
     *
     * @var array<string, string[]>
     */
    private $allowedTypes = [];

    /**
     * List of valid values or a validation callback for each option path.
     * Stored using dot syntax.
     *
     * @var array<string, mixed[]|callable|bool|float|int|string|null>
     */
    private $allowedValues = [];

    /**
     * Stores normalizers for each option path. Stored using dot syntax.
     *
     * @var array<string, callable>
     */
    private $normalizers = [];

    /**
     * @param array<string, mixed> $defaults
     */
    public function setDefaults(array $defaults): void
    {
        $this->defaults = $this->processDefaults($defaults, '');
    }

    /**
     * @param mixed $value
     */
    public function setDefault(string $name, $value): void
    {
        $this->defaults[$name] = $this->processDefaults([$name => $value], '')[$name];
    }

    /**
     * @param mixed $types
     */
    public function setAllowedTypes(string $name, $types): void
    {
        if (\is_string($types)) {
            $this->allowedTypes[$name] = [$types];

            return;
        }

        if (!\is_array($types)) {
            throw new \InvalidArgumentException('Allowed types must be a string or an array of strings.');
        }

        $typeSpecs = [];

        foreach (array_keys($types) as $key) {
            if (!\is_string($types[$key])) {
                throw new \InvalidArgumentException('Allowed types must be strings.');
            }

            $typeSpecs[] = $types[$key];
        }

        $this->allowedTypes[$name] = $typeSpecs;
    }

    /**
     * @param mixed[]|callable|bool|float|int|string|null $values
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
        return array_merge($this->defaults, $this->resolveOnly($options, [], $logger));
    }

    /**
     * Resolves only the options passed as $override and all nested keys that belong to it.
     *
     * @param array<string, mixed> $override
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function resolveOnly(
        array $override = [],
        array $options = [],
        ?LoggerInterface $logger = null
    ): array {
        return $this->applyOptions(array_intersect_key($options, $override), $this->defaults, $override, '', $logger);
    }

    /**
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    private function processDefaults(array $defaults, string $parentPath): array
    {
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($defaults as $option => $value) {
            $path = $parentPath === '' ? $option : $parentPath . '.' . $option;
            /** @mago-ignore analysis:mixed-assignment */
            [$isValid, $processed] = $this->normalizeAndValidate($path, $value);

            if (!$isValid) {
                $defaults[$option] = null;
            } elseif (!Arr::isAssociative($processed)) {
                $defaults[$option] = $processed;
            } else {
                /** @var array<string, mixed> $processed */
                $defaults[$option] = $this->processDefaults($processed, $path);
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $resolved
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function applyOptions(
        array $resolved,
        array $defaults,
        array $options,
        string $parentPath,
        ?LoggerInterface $logger
    ): array {
        /** @mago-ignore analysis:mixed-assignment */
        foreach ($options as $option => $value) {
            $path = $parentPath === '' ? $option : $parentPath . '.' . $option;

            if (!\array_key_exists($option, $defaults)) {
                if ($logger !== null) {
                    $logger->debug(\sprintf('Option "%s" does not exist and will be ignored', $path));
                }

                continue;
            }

            /** @mago-ignore analysis:mixed-assignment */
            $default = $defaults[$option];
            $isBranch = Arr::isAssociative($default);
            /** @mago-ignore analysis:mixed-assignment */
            [$isValid, $value] = $this->normalizeAndValidate($path, $value);

            // If the value is invalid or the value is not in the correct shape, keep the current value if one exists.
            // Otherwise, fall back to the default.
            // For example, we expected to receive a nested value, but we got a scalar
            if (!$isValid || ($isBranch && !\is_array($value))) {
                if ($logger !== null) {
                    $logger->debug(\sprintf('Invalid value for option "%s". The value has been ignored.', $path));
                }

                if (!\array_key_exists($option, $resolved)) {
                    $resolved[$option] = $default;
                }

                continue;
            }

            if (!$isBranch) {
                $resolved[$option] = $value;

                continue;
            }

            $base = $resolved[$option] ?? null;
            if (!\is_array($base)) {
                $base = $default;
            }

            /** @var array<string, mixed> $default */
            /** @var array<string, mixed> $base */
            /** @var array<string, mixed> $value */
            $resolved[$option] = $this->applyOptions($base, $default, $value, $path, $logger);
        }

        return $resolved;
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
        /** @mago-ignore analysis:mixed-assignment */
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

            foreach (array_keys($value) as $key) {
                if (!$this->valueMatchesType($value[$key], $elementType)) {
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
            return $allowedValue($value) === true;
        }

        if (!\is_array($allowedValue)) {
            return $value === $allowedValue;
        }

        return \in_array($value, $allowedValue, true);
    }
}
