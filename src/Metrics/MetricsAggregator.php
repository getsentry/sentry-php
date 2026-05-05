<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Client;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Metrics\Types\CounterMetric;
use Sentry\Metrics\Types\DistributionMetric;
use Sentry\Metrics\Types\GaugeMetric;
use Sentry\Metrics\Types\Metric;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Unit;
use Sentry\Util\TelemetryStorage;

/**
 * @internal
 */
final class MetricsAggregator
{
    /**
     * @var int
     */
    public const METRICS_BUFFER_SIZE = 1000;

    private const METRIC_TYPES = [
        CounterMetric::TYPE => CounterMetric::class,
        DistributionMetric::TYPE => DistributionMetric::class,
        GaugeMetric::TYPE => GaugeMetric::class,
    ];

    /**
     * @var TelemetryStorage<Metric>|null
     */
    private $metrics;

    /**
     * @param int|float                            $value
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
        $metricFlushThreshold = null;

        if (!\is_int($value) && !\is_float($value)) {
            if ($client !== null) {
                $client->getOptions()->getLoggerOrNullLogger()->debug('Metrics value is neither int nor float. Metric will be discarded');
            }

            return;
        }

        if ($client !== null) {
            $options = $client->getOptions();
            $metricFlushThreshold = $options->getMetricFlushThreshold();

            if ($options->getEnableMetrics() === false) {
                return;
            }

            $defaultAttributes = [
                'sentry.environment' => $options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT,
                'server.address' => $options->getServerName(),
            ];

            if ($client instanceof Client) {
                $defaultAttributes['sentry.sdk.name'] = $client->getSdkIdentifier();
                $defaultAttributes['sentry.sdk.version'] = $client->getSdkVersion();
            }

            if ($options->shouldSendDefaultPii()) {
                $hub->configureScope(static function (Scope $scope) use (&$defaultAttributes) {
                    $user = $scope->getUser();
                    if ($user !== null) {
                        if ($user->getId() !== null) {
                            $defaultAttributes['user.id'] = $user->getId();
                        }
                        if ($user->getEmail() !== null) {
                            $defaultAttributes['user.email'] = $user->getEmail();
                        }
                        if ($user->getUsername() !== null) {
                            $defaultAttributes['user.name'] = $user->getUsername();
                        }
                    }
                });
            }

            $release = $options->getRelease();
            if ($release !== null) {
                $defaultAttributes['sentry.release'] = $release;
            }

            $attributes += $defaultAttributes;
        }

        $traceContext = $this->getTraceContext($hub);
        $traceId = new TraceId($traceContext['trace_id']);
        $spanId = new SpanId($traceContext['span_id']);

        $metricTypeClass = self::METRIC_TYPES[$type];
        /** @var Metric $metric */
        $metric = new $metricTypeClass($name, $value, $traceId, $spanId, $attributes, microtime(true), $unit);

        if ($client !== null) {
            $beforeSendMetric = $client->getOptions()->getBeforeSendMetricCallback();
            $metric = $beforeSendMetric($metric);
            if ($metric === null) {
                return;
            }
        }

        $metrics = $this->getStorage($metricFlushThreshold);
        $metrics->push($metric);

        if ($metricFlushThreshold !== null && \count($metrics) >= $metricFlushThreshold) {
            $this->flush($hub);
        }
    }

    public function flush(?HubInterface $hub = null): ?EventId
    {
        if ($this->metrics === null || $this->metrics->isEmpty()) {
            return null;
        }

        $hub = $hub ?? SentrySdk::getCurrentHub();
        $event = Event::createMetrics()->setMetrics($this->metrics->drain());

        return $hub->captureEvent($event);
    }

    /**
     * @return array{trace_id: string, span_id: string}
     */
    private function getTraceContext(HubInterface $hub): array
    {
        $traceContext = null;

        $hub->configureScope(static function (Scope $scope) use (&$traceContext): void {
            $traceContext = $scope->getTraceContext();
        });

        /** @var array{trace_id: string, span_id: string} $traceContext */
        return $traceContext;
    }

    /**
     * @return TelemetryStorage<Metric>
     */
    private function getStorage(?int $metricFlushThreshold = null): TelemetryStorage
    {
        if ($this->metrics === null) {
            /** @var TelemetryStorage<Metric> $metrics */
            $metrics = $metricFlushThreshold !== null
                ? TelemetryStorage::unbounded()
                : TelemetryStorage::bounded(self::METRICS_BUFFER_SIZE);

            $this->metrics = $metrics;
        }

        return $this->metrics;
    }
}
