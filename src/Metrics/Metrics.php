<?php

declare(strict_types=1);

namespace Sentry\Metrics;

use Sentry\Event;
use Sentry\EventId;
use Sentry\SentrySdk;

class Metrics
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
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client === null) {
            return null;
        }

        $event = Event::createMetric();
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 'c:custom/' . $name . '@none',
            'type' => 'c',
            'value' => $value,
            'tags' => $tags,
        ];
        $event->setMetric($metric);

        return $client->captureEvent($event);
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function distribution(string $name, $value, array $tags, ?string $unit = null): ?EventId
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client === null) {
            return null;
        }

        $event = Event::createMetric();
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 'd:custom/' . $name . '@' . ($unit ?? 'none'),
            'type' => 'd',
            'value' => $value,
            'tags' => $tags,
        ];
        $event->setMetric($metric);

        return $client->captureEvent($event);
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function set(string $name, $value, array $tags): ?EventId
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client === null) {
            return null;
        }

        $event = Event::createMetric();
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 's:custom/' . $name . '@none',
            'type' => 's',
            'value' => $value,
            'tags' => $tags,
        ];
        $event->setMetric($metric);

        return $client->captureEvent($event);
    }

    /**
     * @param int|float $value
     * @param string[]  $tags
     */
    public function gauge(string $name, $value, array $tags): ?EventId
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client === null) {
            return null;
        }

        $event = Event::createMetric();
        $metric = [
            'timestamp' => time(),
            'width' => 0,
            'name' => 'g:custom/' . $name . '@none',
            'type' => 'g',
            'value' => $value,
            'tags' => $tags,
        ];
        $event->setMetric($metric);

        return $client->captureEvent($event);
    }
}
