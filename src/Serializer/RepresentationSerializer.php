<?php

declare(strict_types=1);

namespace Sentry\Serializer;

/**
 * Serializes a value into a representation that should reasonably suggest
 * both the type and value, and be serializable into JSON.
 */
class RepresentationSerializer extends AbstractSerializer implements RepresentationSerializerInterface
{
    /**
     * {@inheritdoc}
     */
    public function representationSerialize($value)
    {
        $value = $this->serializeRecursively($value);

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    /**
     * This method is overridden to return even basic types as strings.
     *
     * @param mixed $value The value that needs to be serialized
     *
     * @return string
     */
    protected function serializeValue($value)
    {
        if ($value === null) {
            return 'null';
        }

        if ($value === false) {
            return 'false';
        }

        if ($value === true) {
            return 'true';
        }

        if (\is_float($value)) {
            if (is_nan($value)) {
                return 'NAN';
            }

            if ($this->isCastableToInt($value) && (int) $value == $value) {
                return $value . '.0';
            }
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return (string) parent::serializeValue($value);
    }

    /**
     * Casting a non-finite float, or one that falls outside of the integer
     * range, raises a warning as of PHP 8.5.
     *
     * @param float $value The value to check
     */
    private function isCastableToInt(float $value): bool
    {
        return $value >= (float) \PHP_INT_MIN && $value < (float) \PHP_INT_MAX;
    }
}
