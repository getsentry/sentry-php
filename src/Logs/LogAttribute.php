<?php

declare(strict_types=1);

namespace Sentry\Logs;

/**
 * @phpstan-type AttributeType 'string'|'boolean'|'integer'|'double'
 * @phpstan-type AttributeValue string|bool|int|float
 * @phpstan-type AttributeSerialized array{
 *     type: AttributeType,
 *     value: AttributeValue
 * }
 */
class LogAttribute implements \JsonSerializable
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

    /**
     * @return AttributeSerialized
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
        ];
    }

    public function __toString(): string
    {
        return "{$this->value} ({$this->type})";
    }
}
