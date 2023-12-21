<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\EventId;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Metrics\Types\SetType;

final class Metrics
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
     * @param int|float $value
     * @param string[]  $tags
     */
    public function increment(
        string $key,
        $value,
        ?MetricsUnit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
        $this->aggregator->add(
            CounterType::TYPE,
            $key,
            $value,
            $unit,
            $tags,
            $timestamp,
            $stackLevel
        );
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function distribution(
        string $key,
        $value,
        ?MetricsUnit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
        $this->aggregator->add(
            DistributionType::TYPE,
            $key,
            $value,
            $unit,
            $tags,
            $timestamp,
            $stackLevel
        );
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function gauge(
        string $key,
        $value,
        ?MetricsUnit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
        $this->aggregator->add(
            GaugeType::TYPE,
            $key,
            $value,
            $unit,
            $tags,
            $timestamp,
            $stackLevel
        );
    }

    /**
     * @param int|string $value
     * @param string[]   $tags
     */
    public function set(
        string $key,
        $value,
        ?MetricsUnit $unit = null,
        array $tags = [],
        ?int $timestamp = null,
        int $stackLevel = 0
    ): void {
        $this->aggregator->add(
            SetType::TYPE,
            $key,
            $value,
            $unit,
            $tags,
            $timestamp,
            $stackLevel
        );
    }

    public function flush(): ?EventId
    {
        return $this->aggregator->flush();
    }
}
