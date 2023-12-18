<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Event;
use Sentry\EventId;
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
     * @var array
     */
    private $buckets = [];

    private const METRIC_TYPES = [
        CounterType::TYPE => CounterType::class,
        DistributionType::TYPE => DistributionType::class,
        GaugeType::TYPE => GaugeType::class,
        SetType::TYPE => SetType::class,
    ];

    /**
     * @param mixed $value int|float|string
     */
    public function add(
        string $type,
        string $key,
        $value,
        ?MetricsUnit $unit,
        array $tags,
        ?int $timestamp,
        int $stackLevel,
    ): void {
        if ($timestamp === null) {
            $timestamp = time();
        }
        if ($unit === null) {
            $unit = MetricsUnit::none();
        }

        $tags = $this->serializeTags($tags);

        $bucketTimestamp = floor($timestamp / self::ROLLUP_IN_SECONDS);
        $bucketKey = crc32(
            $type .
            $key .
            $unit .
            $tags .
            $bucketTimestamp
        );

        if (\array_key_exists($bucketKey, $this->buckets)) {
            $metric = $this->buckets[$bucketKey];
            $metric->add($value);
        } else {
            $metric = new (self::METRIC_TYPES[$type])(
                $key, $value, $unit, $tags, $timestamp
            );
            $this->buckets[$bucketKey] = $metric;
        }

        if (!$metric->hasCodeLocation()) {
            $metric->addCodeLocation($stackLevel);
        }
    }

    public function flush(): ?EventId
    {
        $hub = SentrySdk::getCurrentHub();
        $event = Event::createStatsd()
            ->setMetrics($this->buckets);

        $this->buckets = [];

        return $hub->captureEvent($event);
    }

    private function serializeTags(array $tags): string
    {
        $hub = SentrySdk::getCurrentHub();
        $options = $hub->getClient()->getOptions();

        $defaultTags = [
            'environment' => $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
        ];

        $release = $options->getRelease();
        if ($release !== null) {
            $defaultTags['release'] = $options->getRelease();
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

        // It's very important to sort the tags in order to obtain the same bucket key.
        ksort($tags);

        return str_replace('=', ':', http_build_query(
            $tags, '', ','
        ));
    }
}
