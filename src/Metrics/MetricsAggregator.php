<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Event;
use Sentry\EventId;
use Sentry\Metrics\Types\AbstractType;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Metrics\Types\SetType;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tracing\TransactionSource;

/**
 * @internal
 */
final class MetricsAggregator
{
    /**
     * @var int
     */
    private const ROLLUP_IN_SECONDS = 10;

    /**
     * @var array<string, AbstractType>
     */
    private $buckets = [];

    private const METRIC_TYPES = [
        CounterType::TYPE => CounterType::class,
        DistributionType::TYPE => DistributionType::class,
        GaugeType::TYPE => GaugeType::class,
        SetType::TYPE => SetType::class,
    ];

    /**
     * @param array<string, string> $tags
     * @param int|float|string      $value
     */
    public function add(
        string $type,
        string $key,
        $value,
        ?MetricsUnit $unit,
        array $tags,
        ?int $timestamp,
        int $stackLevel
    ): void {
        if ($timestamp === null) {
            $timestamp = time();
        }
        if ($unit === null) {
            $unit = MetricsUnit::none();
        }

        $tags = $this->serializeTags($tags);

        $bucketTimestamp = floor($timestamp / self::ROLLUP_IN_SECONDS);
        $bucketKey = md5(
            $type .
            $key .
            $unit .
            serialize($tags) .
            $bucketTimestamp
        );

        if (\array_key_exists($bucketKey, $this->buckets)) {
            $metric = $this->buckets[$bucketKey];
            $metric->add($value);
        } else {
            $metricTypeClass = self::METRIC_TYPES[$type];
            /** @var AbstractType $metric */
            /** @phpstan-ignore-next-line SetType accepts int|float|string, others only int|float */
            $metric = new $metricTypeClass($key, $value, $unit, $tags, $timestamp);
            $this->buckets[$bucketKey] = $metric;
        }

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client !== null) {
            $options = $client->getOptions();

            if (
                $options->shouldAttachMetricCodeLocations()
                && !$metric->hasCodeLocation()
            ) {
                $metric->addCodeLocation($stackLevel);
            }
        }

        $span = $hub->getSpan();
        if ($span !== null) {
            $span->setMetricsSummary($type, $key, $value, $unit, $tags);
        }
    }

    public function flush(): ?EventId
    {
        if ($this->buckets === []) {
            return null;
        }

        $hub = SentrySdk::getCurrentHub();
        $event = Event::createMetrics()->setMetrics($this->buckets);

        $this->buckets = [];

        return $hub->captureEvent($event);
    }

    /**
     * @param array<string, string> $tags
     *
     * @return array<string, string>
     */
    private function serializeTags(array $tags): array
    {
        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client !== null) {
            $options = $client->getOptions();

            $defaultTags = [
                'environment' => $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
            ];

            $release = $options->getRelease();
            if ($release !== null) {
                $defaultTags['release'] = $release;
            }

            $hub->configureScope(function (Scope $scope) use (&$defaultTags) {
                $transaction = $scope->getTransaction();
                if (
                    $transaction !== null
                    // Only include the transaction name if it has good quality
                    && $transaction->getMetadata()->getSource() !== TransactionSource::url()
                ) {
                    $defaultTags['transaction'] = $transaction->getName();
                }
            });

            $tags = array_merge($defaultTags, $tags);
        }

        // It's very important to sort the tags in order to obtain the same bucket key.
        ksort($tags);

        return $tags;
    }
}
