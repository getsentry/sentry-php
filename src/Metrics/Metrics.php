<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\EventId;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Tracing\SpanContext;

use function Sentry\trace;

class Metrics
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var MetricsAggregator
     */
    private $aggregator;

    private function __construct()
    {
        $this->aggregator = new MetricsAggregator();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array<string, string> $tags
     *
     * @deprecated Use Metrics::count() instead. To be removed in 5.x.
     */
    public function increment(
        string $key,
        float $value,
        ?Unit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    /**
     * @param array<string, int|float|string|bool> $attributes
     */
    public function count(
        string $name,
        float $value,
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
     * @param array<string, int|float|string|bool> $attributes
     */
    public function distribution(
        string $name,
        float $value,
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
     * @param array<string, int|float|string|bool> $attributes
     */
    public function gauge(
        string $name,
        float $value,
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

    /**
     * @param int|string            $value
     * @param array<string, string> $tags
     *
     * @deprecated To be removed in 5.x.
     */
    public function set(
        string $key,
        $value,
        ?Unit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    /**
     * @template T
     *
     * @param callable(): T         $callback
     * @param array<string, string> $tags
     *
     * @return T
     *
     * @deprecated To be removed in 5.x.
     */
    public function timing(
        string $key,
        callable $callback,
        array $tags = [],
        int $stackLevel = 0
    ) {
        return trace(
            function () use ($callback) {
                return $callback();
            },
            SpanContext::make()
                ->setOp('metric.timing')
                ->setOrigin('auto.measure.metrics.timing')
                ->setDescription($key)
        );
    }

    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }
}
