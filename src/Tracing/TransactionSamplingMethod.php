<?php

declare(strict_types=1);

namespace Sentry\Tracing;

/**
 * This enum represents all the possible types of transaction sources.
 */
final class TransactionSamplingMethod implements \Stringable
{
    /**
     * @var string The value of the enum instance
     */
    private $value;

    /**
     * @var array<string, self> A list of cached enum instances
     */
    private static $instances = [];

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function explicitlySet(): self
    {
        return self::getInstance('explicitly_set');
    }

    public static function clientSampler(): self
    {
        return self::getInstance('client_sampler');
    }

    public static function clientRate(): self
    {
        return self::getInstance('client_rate');
    }

    public static function inheritance(): self
    {
        return self::getInstance('inheritance');
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
