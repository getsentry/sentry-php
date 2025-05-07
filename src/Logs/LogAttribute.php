<?php

declare(strict_types=1);

namespace Sentry\Logs;

/**
 * @phpstan-type AttributeType 'string'|'boolean'|'integer'|'double'
 * @phpstan-type AttributeValue string|bool|int|float
 * @phpstan-type AttributeSerialized array{
 *      type: AttributeType,
 *      value: AttributeValue
 *  }
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
     * @param AttributeValue $value
     */
    public static function fromValue($value): self
    {
        if (\is_bool($value)) {
            return new self($value, 'boolean');
        }

        if (\is_int($value)) {
            return new self($value, 'integer');
        }

        if (\is_float($value)) {
            return new self($value, 'double');
        }

        return new self((string) $value, 'string');
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
}
