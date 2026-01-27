<?php

declare(strict_types=1);

namespace Sentry\State;

/**
 * Enum-like value object representing a scope type.
 */
final class ScopeType
{
    /**
     * @var string The value of the enum instance
     */
    private $value;

    /**
     * @var array<string, self>
     */
    private static $instances = [];

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function global(): self
    {
        return self::getInstance('global');
    }

    public static function isolation(): self
    {
        return self::getInstance('isolation');
    }

    public static function current(): self
    {
        return self::getInstance('current');
    }

    public static function merged(): self
    {
        return self::getInstance('merged');
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function getInstance(string $value): self
    {
        if (!isset(self::$instances[$value])) {
            self::$instances[$value] = new self($value);
        }

        return self::$instances[$value];
    }
}
