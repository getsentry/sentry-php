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
use Sentry\SentrySdk;

use function Sentry\trace_metrics;

final class TraceMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        SentrySdk::init(new Client(new Options(), StubTransport::getInstance()));
        StubTransport::$events = [];
    }

    public function testCounterMetrics(): void
    {
        trace_metrics()->count('test-count', 2, ['foo' => 'bar']);
        trace_metrics()->count('test-count', 2, ['foo' => 'bar']);
        trace_metrics()->flush();

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
        trace_metrics()->gauge('test-gauge', 10, ['foo' => 'bar']);
        trace_metrics()->flush();

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
        trace_metrics()->distribution('test-distribution', 10, ['foo' => 'bar']);
        trace_metrics()->flush();
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
            trace_metrics()->count('test', 1, ['foo' => 'bar']);
        }
        trace_metrics()->flush();
        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];
        $metrics = $event->getMetrics();
        $this->assertCount(MetricsAggregator::METRICS_BUFFER_SIZE, $metrics);
    }

    public function testEnableMetrics(): void
    {
        SentrySdk::init(new Client(new Options([
            'enable_metrics' => false,
        ]), StubTransport::getInstance()));

        trace_metrics()->count('test-count', 2, ['foo' => 'bar']);
        trace_metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
    }

    public function testBeforeSendMetricAltersContent()
    {
        SentrySdk::init(new Client(new Options([
            'before_send_metric' => static function (Metric $metric) {
                $metric->setValue(99999);

                return $metric;
            },
        ]), StubTransport::getInstance()));

        trace_metrics()->count('test-count', 2, ['foo' => 'bar']);
        trace_metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(1, $event->getMetrics());
        $metric = $event->getMetrics()[0];
        $this->assertEquals(99999, $metric->getValue());
    }

    public function testIntType()
    {
        trace_metrics()->count('test-count', 2, ['foo' => 'bar']);
        trace_metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(1, $event->getMetrics());
        $metric = $event->getMetrics()[0];

        $this->assertEquals('test-count', $metric->getName());
        $this->assertEquals(2, $metric->getValue());
    }

    public function testFloatType(): void
    {
        trace_metrics()->gauge('test-gauge', 10.50, ['foo' => 'bar']);
        trace_metrics()->flush();

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
        trace_metrics()->count('test-count', 'test-value');
        trace_metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
    }
}
