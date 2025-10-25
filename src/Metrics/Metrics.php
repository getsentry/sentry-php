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
     * @deprecated Metrics are no longer supported. Metrics API is a no-op and will be removed in 5.x.
     */
    public function increment(
        string $key,
        float $value,
        ?MetricsUnit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
    }

    public function count(
        string $name,
        float $value,
        ?MetricsUnit $unit = null,
        array $attributes = [],
        ?float $timestamp = null
    ): void {
        $this->aggregator->add(
            CounterType::TYPE,
            $name,
            $value,
            $unit,
            $attributes,
            $timestamp
        );
    }

    public function distribution(
        string $name,
        float $value,
        ?MetricsUnit $unit = null,
        array $attributes = [],
        ?float $timestamp = null
    ): void {
        $this->aggregator->add(
            DistributionType::TYPE,
            $name,
            $value,
            $unit,
            $attributes,
            $timestamp
        );
    }

    public function gauge(
        string $name,
        float $value,
        ?MetricsUnit $unit = null,
        array $attributes = [],
        ?float $timestamp = null
    ): void {
        $this->aggregator->add(
            GaugeType::TYPE,
            $name,
            $value,
            $unit,
            $attributes,
            $timestamp
        );
    }

    /**
     * @param int|string            $value
     * @param array<string, string> $tags
     *
     * @deprecated Metrics are no longer supported. Metrics API is a no-op and will be removed in 5.x.
     */
    public function set(
        string $key,
        $value,
        ?MetricsUnit $unit = null,
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
     * @deprecated Metrics are no longer supported. Metrics API is a no-op and will be removed in 5.x.
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
