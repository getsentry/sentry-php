<?php

declare(strict_types=1);

namespace Sentry\Attributes;

/**
 * @phpstan-type AttributeType 'string'|'boolean'|'integer'|'double'
 * @phpstan-type AttributeValue string|bool|int|float
 */
class Attribute
{
    /**
     * @var AttributeType
     */
    private $type;

    /**
     * @var AttributeValue
     */
    private $value;

    /**
     * @param AttributeValue $value
     * @param AttributeType  $type
     */
    public function __construct($value, string $type)
    {
        $this->value = $value;
        $this->type = $type;
    }

    /**
     * @return AttributeType
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return AttributeValue
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     *
     * @throws \InvalidArgumentException thrown when the value cannot be serialized as an attribute
     */
    public static function fromValue($value): self
    {
        $attribute = self::tryFromValue($value);

        if ($attribute === null) {
            throw new \InvalidArgumentException(\sprintf('Invalid attribute value, %s cannot be serialized', \gettype($value)));
        }

        return $attribute;
    }

    /**
     * @param mixed $value
     */
    public static function tryFromValue($value): ?self
    {
        if ($value === null) {
            return null;
        }

        if (\is_bool($value)) {
            return new self($value, 'boolean');
        }

        if (\is_int($value)) {
            return new self($value, 'integer');
        }

        if (\is_float($value)) {
            return new self($value, 'double');
        }

        if (\is_string($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            $stringValue = (string) $value;

            if (empty($stringValue)) {
                return null;
            }

            return new self($stringValue, 'string');
        }

        return null;
    }

    public function __toString(): string
    {
        return "{$this->value} ({$this->type})";
    }
}
