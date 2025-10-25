<?php

declare(strict_types=1);

namespace Sentry\Metrics\Types;

use Sentry\Metrics\MetricsUnit;

/**
 * @internal
 */
final class CounterType extends AbstractType
{
    /**
     * @var string
     */
    public const TYPE = 'counter';

    /**
     * @var int|float
     */
    private $value;

    /**
     * @param int|float            $value
     * @param array<string, mixed> $attributes
     */
    public function __construct(string $name, $value, MetricsUnit $unit, array $attributes, float $timestamp)
    {
        parent::__construct($name, $unit, $attributes, $timestamp);

        $this->value = (float) $value;
    }

    /**
     * @param int|float $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
