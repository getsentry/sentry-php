<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Event;
use Sentry\EventId;
use Sentry\Metrics\Types\AbstractType;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\SentrySdk;

/**
 * @internal
 */
final class MetricsAggregator
{
    /**
     * @var array<string, AbstractType>
     */
    private $metrics = [];

    private const METRIC_TYPES = [
        CounterType::TYPE => CounterType::class,
        DistributionType::TYPE => DistributionType::class,
        GaugeType::TYPE => GaugeType::class,
    ];

    /**
     * @param int|float|string $value
     */
    public function add(
        string $type,
        string $name,
        $value,
        ?MetricsUnit $unit,
        array $attributes,
        ?float $timestamp
    ): void {
        if ($timestamp === null) {
            $timestamp = microtime(true);
        }
        if ($unit === null) {
            $unit = MetricsUnit::none();
        }

        $metricTypeClass = self::METRIC_TYPES[$type];
        /** @var AbstractType $metric */
        /** @phpstan-ignore-next-line SetType accepts int|float|string, others only int|float */
        $metric = new $metricTypeClass($name, $value, $unit, $attributes, $timestamp);
        $this->metrics[] = $metric;
    }

    public function flush(): ?EventId
    {
        if ($this->metrics === []) {
            return null;
        }

        $hub = SentrySdk::getCurrentHub();
        $event = Event::createMetrics()->setMetrics($this->metrics);

        $this->metrics = [];

        return $hub->captureEvent($event);
    }
}
