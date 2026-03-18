<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Metrics\MetricsAggregator;
use Sentry\Metrics\Types\CounterMetric;
use Sentry\Metrics\Types\DistributionMetric;
use Sentry\Metrics\Types\GaugeMetric;
use Sentry\Metrics\Types\Metric;
use Sentry\Options;
use Sentry\State\HubAdapter;

use function Sentry\metrics;

final class TraceMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        HubAdapter::getInstance()->bindClient(new Client(new Options(), StubTransport::getInstance()));
        StubTransport::$events = [];
    }

    public function testCounterMetrics(): void
    {
        metrics()->count('test-count', 2, ['foo' => 'bar']);
        metrics()->count('test-count', 2, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];
        $this->assertCount(2, $event->getMetrics());
        $metrics = $event->getMetrics();
        $metric = $metrics[0];
        $this->assertEquals('test-count', $metric->getName());
        $this->assertEquals(CounterMetric::TYPE, $metric->getType());
        $this->assertEquals(2, $metric->getValue());
        $this->assertArrayHasKey('foo', $metric->getAttributes()->toSimpleArray());
    }

    public function testGaugeMetrics(): void
    {
        metrics()->gauge('test-gauge', 10, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];
        $this->assertCount(1, $event->getMetrics());
        $metrics = $event->getMetrics();
        $metric = $metrics[0];
        $this->assertEquals('test-gauge', $metric->getName());
        $this->assertEquals(GaugeMetric::TYPE, $metric->getType());
        $this->assertEquals(10, $metric->getValue());
        $this->assertArrayHasKey('foo', $metric->getAttributes()->toSimpleArray());
    }

    public function testDistributionMetrics(): void
    {
        metrics()->distribution('test-distribution', 10, ['foo' => 'bar']);
        metrics()->flush();
        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];
        $this->assertCount(1, $event->getMetrics());
        $metrics = $event->getMetrics();
        $metric = $metrics[0];
        $this->assertEquals('test-distribution', $metric->getName());
        $this->assertEquals(DistributionMetric::TYPE, $metric->getType());
        $this->assertEquals(10, $metric->getValue());
        $this->assertArrayHasKey('foo', $metric->getAttributes()->toSimpleArray());
    }

    public function testMetricsBufferFull(): void
    {
        for ($i = 0; $i < MetricsAggregator::METRICS_BUFFER_SIZE + 100; ++$i) {
            metrics()->count('test', 1, ['foo' => 'bar']);
        }
        metrics()->flush();
        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];
        $metrics = $event->getMetrics();
        $this->assertCount(MetricsAggregator::METRICS_BUFFER_SIZE, $metrics);
    }

    public function testEnableMetrics(): void
    {
        HubAdapter::getInstance()->bindClient(new Client(new Options([
            'enable_metrics' => false,
        ]), StubTransport::getInstance()));

        metrics()->count('test-count', 2, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
    }

    public function testBeforeSendMetricAltersContent()
    {
        HubAdapter::getInstance()->bindClient(new Client(new Options([
            'before_send_metric' => static function (Metric $metric) {
                $metric->setValue(99999);

                return $metric;
            },
        ]), StubTransport::getInstance()));

        metrics()->count('test-count', 2, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(1, $event->getMetrics());
        $metric = $event->getMetrics()[0];
        $this->assertEquals(99999, $metric->getValue());
    }

    public function testIntType()
    {
        metrics()->count('test-count', 2, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(1, $event->getMetrics());
        $metric = $event->getMetrics()[0];

        $this->assertEquals('test-count', $metric->getName());
        $this->assertEquals(2, $metric->getValue());
    }

    public function testFloatType(): void
    {
        metrics()->gauge('test-gauge', 10.50, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(1, $event->getMetrics());
        $metric = $event->getMetrics()[0];

        $this->assertEquals('test-gauge', $metric->getName());
        $this->assertEquals(10.50, $metric->getValue());
    }

    public function testInvalidTypeIsDiscarded(): void
    {
        // @phpstan-ignore-next-line
        metrics()->count('test-count', 'test-value');
        metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
    }
}
