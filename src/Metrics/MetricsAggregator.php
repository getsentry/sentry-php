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
use Sentry\State\Scope;

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
        array $attributes,
        ?MetricsUnit $unit
    ): void {
        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();

        if ($client !== null) {
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

            $attributes = array_merge($defaultAttributes, $attributes);
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
