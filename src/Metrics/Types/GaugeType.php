<?php

declare(strict_types=1);

namespace Sentry\Metrics\Types;

use Sentry\Metrics\MetricsUnit;

/**
 * @internal
 */
final class GaugeType extends AbstractType
{
    /**
     * @var string
     */
    public const TYPE = 'g';

    /**
     * @var int|float
     */
    private $last;

    /**
     * @var int|float
     */
    private $min;

    /**
     * @var int|float
     */
    private $max;

    /**
     * @var int|float
     */
    private $sum;

    /**
     * @var int
     */
    private $count;

    /**
     * @param int|float $value
     */
    public function __construct(string $key, $value, MetricsUnit $unit, array $tags, int $timestamp)
    {
        parent::__construct($key, $unit, $tags, $timestamp);

        $this->last = $value;
        $this->min = $value;
        $this->max = $value;
        $this->sum = $value;
        $this->count = 1;
    }

    /**
     * @param int|float $value
     */
    public function add($value): void
    {
        $value = (float) $value;

        $this->last = $value;
        $this->min = min($this->min, $value);
        $this->max = max($this->min, $value);
        $this->sum += $value;
        ++$this->count;
    }

    /**
     * @return array<int, float|int>
     */
    public function serialize(): array
    {
        return [
            $this->last,
            $this->min,
            $this->max,
            $this->sum,
            $this->count,
        ];
    }

    public function getType(): string
    {
        return self::TYPE;
    }
}
