<?php

declare(strict_types=1);

namespace Sentry\Attributes;

class AttributeBag
{
    /**
     * @var array<string, Attribute>
     */
    private $attributes = [];

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): self
    {
        $attribute = $value instanceof Attribute
            ? $value
            : Attribute::tryFromValue($value);

        if ($attribute !== null) {
            $this->attributes[$key] = $attribute;
        }

        return $this;
    }

    public function get(string $key): ?Attribute
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @return array<string, Attribute>
     */
    public function all(): array
    {
        return $this->attributes;
    }
}
