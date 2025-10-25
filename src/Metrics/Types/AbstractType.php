<?php

declare(strict_types=1);

namespace Sentry\Metrics\Types;

use Sentry\Attributes\AttributeBag;
use Sentry\Metrics\MetricsUnit;

/**
 * @internal
 */
abstract class AbstractType
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var MetricsUnit
     */
    private $unit;

    /**
     * @var float
     */
    private $timestamp;

    /**
     * @var AttributeBag
     */
    private $attributes;

    /**
     * @param array<string, string> $attributes
     */
    public function __construct(string $name, MetricsUnit $unit, array $attributes, float $timestamp)
    {
        $this->name = $name;
        $this->unit = $unit;
        $this->timestamp = $timestamp;
        $this->attributes = new AttributeBag();

        foreach ($attributes as $attribute) {
            $this->attributes->set($attribute['name'], $attribute['value']);
        }
    }

    abstract public function setValue($value): void;

    abstract public function getType(): string;

    abstract public function getValue();

    public function getName(): string
    {
        return $this->name;
    }

    public function getUnit(): MetricsUnit
    {
        return $this->unit;
    }

    public function getAttributes(): AttributeBag
    {
        return $this->attributes;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
}
