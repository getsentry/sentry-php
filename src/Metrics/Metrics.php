<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;

/**
 * This is an experimental feature and should neither be used nor considered stable.
 * The API might change at any time without prior warning.
 *
 * @internal
 */
final class Metrics
{
    /**
     * @var self|null
     */
    private static $instance;

    private function __construct()
    {
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
    public function incr(string $name, $value, array $tags): ?EventId
    {
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 'c:custom/' . $name . '@none',
            'type' => 'c',
            'value' => $value,
            'tags' => $tags,
        ];

        return $this->sendMetric($metric);
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function distribution(string $name, $value, array $tags, ?MetricsUnit $unit = null): ?EventId
    {
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 'd:custom/' . $name . '@' . ($unit ?? 'none'),
            'type' => 'd',
            'value' => $value,
            'tags' => $tags,
        ];

        return $this->sendMetric($metric);
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function set(string $name, $value, array $tags): ?EventId
    {
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 's:custom/' . $name . '@none',
            'type' => 's',
            'value' => $value,
            'tags' => $tags,
        ];

        return $this->sendMetric($metric);
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function gauge(string $name, $value, array $tags): ?EventId
    {
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 'g:custom/' . $name . '@none',
            'type' => 'g',
            'value' => $value,
            'tags' => $tags,
        ];

        return $this->sendMetric($metric);
    }

    /**
     * @param array<string, array<string>|float|int|string> $metric
     */
    private function sendMetric(array $metric): ?EventId
    {
        $event = Event::createMetric()
            ->setMetric($metric);

        $hub = SentrySdk::getCurrentHub();

        return $hub->captureEvent($event);
    }
}
