<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Metrics\Types\AbstractType;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Util\RingBuffer;

/**
 * @internal
 */
final class MetricsAggregator
{
    /**
     * @var int
     */
    public const METRICS_BUFFER_SIZE = 1000;

    /**
     * @var RingBuffer<AbstractType>
     */
    private $metrics;

    public function __construct()
    {
        $this->metrics = new RingBuffer(self::METRICS_BUFFER_SIZE);
    }

    private const METRIC_TYPES = [
        CounterType::TYPE => CounterType::class,
        DistributionType::TYPE => DistributionType::class,
        GaugeType::TYPE => GaugeType::class,
    ];

    /**
     * @param int|float|string                     $value
     * @param array<string, int|float|string|bool> $attributes
     */
    public function add(
        string $type,
        string $name,
        $value,
        array $attributes,
        ?Unit $unit
    ): void {
        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client instanceof Client) {
            $options = $client->getOptions();

            $defaultAttributes = [
                'sentry.sdk.name' => $client->getSdkIdentifier(),
                'sentry.sdk.version' => $client->getSdkVersion(),
                'sentry.environment' => $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
            ];

            $release = $options->getRelease();
            if ($release !== null) {
                $defaultAttributes['sentry.release'] = $release;
            }

            $attributes += $defaultAttributes;
        }

        $spanId = null;
        $traceId = null;

        $span = $hub->getSpan();
        if ($span !== null) {
            $spanId = $span->getSpanId();
            $traceId = $span->getTraceId();
        } else {
            $hub->configureScope(function (Scope $scope) use (&$traceId, &$spanId) {
                $propagationContext = $scope->getPropagationContext();
                $traceId = $propagationContext->getTraceId();
                $spanId = $propagationContext->getSpanId();
            });
        }

        $metricTypeClass = self::METRIC_TYPES[$type];
        /** @var AbstractType $metric */
        /** @phpstan-ignore-next-line SetType accepts int|float|string, others only int|float */
        $metric = new $metricTypeClass($name, $value, $traceId, $spanId, $attributes, microtime(true), $unit);

        $this->metrics->push($metric);
    }

    public function flush(): ?EventId
    {
        if ($this->metrics->isEmpty()) {
            return null;
        }

        $hub = SentrySdk::getCurrentHub();
        $event = Event::createMetrics()->setMetrics($this->metrics->drain());

        return $hub->captureEvent($event);
    }
}
