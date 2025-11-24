<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\EventId;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;

class TraceMetrics
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var MetricsAggregator
     */
    private $aggregator;

    public function __construct()
    {
        $this->aggregator = new MetricsAggregator();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new TraceMetrics();
        }

        return self::$instance;
    }

    /**
     * @param int|float                            $value
     * @param array<string, int|float|string|bool> $attributes
     */
    public function count(
        string $name,
        $value,
        array $attributes = [],
        ?Unit $unit = null
    ): void {
        $this->aggregator->add(
            CounterType::TYPE,
            $name,
            $value,
            $attributes,
            $unit
        );
    }

    /**
     * @param int|float                            $value
     * @param array<string, int|float|string|bool> $attributes
     */
    public function distribution(
        string $name,
        $value,
        array $attributes = [],
        ?Unit $unit = null
    ): void {
        $this->aggregator->add(
            DistributionType::TYPE,
            $name,
            $value,
            $attributes,
            $unit
        );
    }

    /**
     * @param int|float                            $value
     * @param array<string, int|float|string|bool> $attributes
     */
    public function gauge(
        string $name,
        $value,
        array $attributes = [],
        ?Unit $unit = null
    ): void {
        $this->aggregator->add(
            GaugeType::TYPE,
            $name,
            $value,
            $attributes,
            $unit
        );
    }

    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }
}
