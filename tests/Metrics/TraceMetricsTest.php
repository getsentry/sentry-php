<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\Metrics\MetricsAggregator;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Options;
use Sentry\State\HubAdapter;

use function Sentry\trace_metrics;

final class TraceMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        HubAdapter::getInstance()->bindClient(new Client(new Options(), new StubTransport()));
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
        $this->assertEquals(CounterType::TYPE, $metric->getType());
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
        $this->assertEquals(GaugeType::TYPE, $metric->getType());
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
        $this->assertEquals(DistributionType::TYPE, $metric->getType());
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
}
