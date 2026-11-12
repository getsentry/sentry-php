<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Metrics\MetricsAggregator;
use Sentry\Metrics\Types\CounterMetric;
use Sentry\Metrics\Types\DistributionMetric;
use Sentry\Metrics\Types\GaugeMetric;
use Sentry\Metrics\Types\Metric;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\Tests\ClientReport\ClientReportAssertions;
use Sentry\UserDataBag;

use function Sentry\metrics;

final class TraceMetricsTest extends TestCase
{
    use ClientReportAssertions;

    protected function setUp(): void
    {
        SentrySdk::init(new Client(new Options(), StubTransport::getInstance()));
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

    public function testFlushesImmediatelyWhenMetricFlushThresholdIsReached(): void
    {
        SentrySdk::init(new Client(new Options([
            'metric_flush_threshold' => 2,
        ]), StubTransport::getInstance()));

        metrics()->count('first-metric', 1, ['foo' => 'bar']);

        $this->assertCount(0, StubTransport::$events);

        metrics()->count('second-metric', 2, ['foo' => 'bar']);

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(2, $event->getMetrics());
        $this->assertSame('first-metric', $event->getMetrics()[0]->getName());
        $this->assertSame('second-metric', $event->getMetrics()[1]->getName());
    }

    public function testDoesNotFlushImmediatelyWhenMetricFlushThresholdIsNull(): void
    {
        SentrySdk::init(new Client(new Options([
            'metric_flush_threshold' => null,
        ]), StubTransport::getInstance()));

        metrics()->count('first-metric', 1, ['foo' => 'bar']);
        metrics()->count('second-metric', 2, ['foo' => 'bar']);

        $this->assertCount(0, StubTransport::$events);

        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $this->assertCount(2, StubTransport::$events[0]->getMetrics());
    }

    public function testGlobalScopeAttributesAreAddedToMetric(): void
    {
        SentrySdk::getGlobalScope()->setUser(UserDataBag::createFromUserIdentifier('global-user'));

        metrics()->count('test-count', 2);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);

        $metric = StubTransport::$events[0]->getMetrics()[0];
        $this->assertSame('global-user', $metric->getAttributes()->get('user.id')->getValue());
    }

    public function testFlushCapturesMetricsWithProvidedClient(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('getOptions')
            ->willReturn(new Options());

        $fallbackClient = $this->createMock(ClientInterface::class);
        $fallbackClient->method('getOptions')
            ->willReturn(new Options());
        $fallbackClient->expects($this->never())
            ->method('captureEvent');
        SentrySdk::init($fallbackClient);

        $aggregator = new MetricsAggregator();
        $aggregator->add(CounterMetric::TYPE, 'test-count', 2, ['foo' => 'bar'], null);

        $client->expects($this->once())
            ->method('captureEvent')
            ->with(
                $this->callback(function (Event $event): bool {
                    $this->assertCount(1, $event->getMetrics());

                    return true;
                })
            );

        $aggregator->flush($client);
    }

    public function testMetricsBufferFullWhenMetricFlushThresholdIsNull(): void
    {
        SentrySdk::init(new Client(new Options([
            'metric_flush_threshold' => null,
        ]), StubTransport::getInstance()));

        for ($i = 0; $i < MetricsAggregator::METRICS_BUFFER_SIZE + 100; ++$i) {
            metrics()->count('test', 1, ['foo' => 'bar']);
        }

        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];
        $metrics = $event->getMetrics();

        $this->assertCount(MetricsAggregator::METRICS_BUFFER_SIZE, $metrics);
    }

    public function testRecordsClientReportWhenMetricsBufferOverflows(): void
    {
        SentrySdk::init(new Client(new Options([
            'metric_flush_threshold' => null,
            'send_client_reports' => true,
        ]), StubTransport::getInstance()));

        for ($i = 0; $i < MetricsAggregator::METRICS_BUFFER_SIZE; ++$i) {
            metrics()->count('test', 1);
        }

        $this->assertClientReports([]);

        metrics()->count('test', 1);

        $this->assertClientReports(['trace_metric' => ['buffer_overflow' => 1]]);

        metrics()->flush();

        $this->assertCount(MetricsAggregator::METRICS_BUFFER_SIZE, StubTransport::$events[0]->getMetrics());
    }

    public function testBeforeSendMetricExceptionDropsMetricAndIsLogged(): void
    {
        StubLogger::$logs = [];
        SentrySdk::init(new Client(new Options([
            'before_send_metric' => static function (): void {
                throw new \RuntimeException('test');
            },
            'logger' => StubLogger::getInstance(),
            'send_client_reports' => true,
        ]), StubTransport::getInstance()));

        metrics()->count('test-count', 2);
        metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertContains([
            'level' => 'error',
            'message' => 'The "before_send_metric" callback failed with exception: "test".',
            'context' => [],
        ], StubLogger::$logs);
        $this->assertClientReports(['trace_metric' => ['before_send' => 1]]);
    }

    public function testRecordsClientReportWhenBeforeSendMetricDiscardsMetric(): void
    {
        SentrySdk::init(new Client(new Options([
            'before_send_metric' => static function (): ?Metric {
                return null;
            },
            'send_client_reports' => true,
        ]), StubTransport::getInstance()));

        metrics()->count('test-count', 2);
        metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
        $this->assertClientReports(['trace_metric' => ['before_send' => 1]]);
    }

    public function testBeforeSendMetricAltersContent(): void
    {
        SentrySdk::init(new Client(new Options([
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

    public function testIntType(): void
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

    public function testNullAttributeValueIsStringified(): void
    {
        metrics()->count('test-count', 2, ['foo' => null]);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $event = StubTransport::$events[0];

        $this->assertCount(1, $event->getMetrics());
        $metric = $event->getMetrics()[0];

        $this->assertSame('null', $metric->getAttributes()->toSimpleArray()['foo']);
    }

    public function testInvalidTypeIsDiscarded(): void
    {
        // @phpstan-ignore-next-line
        metrics()->count('test-count', 'test-value');
        metrics()->flush();

        $this->assertEmpty(StubTransport::$events);
    }

    public function testMetricsUseExternalPropagationContextWhenNoLocalSpanExists(): void
    {
        Scope::registerExternalPropagationContext(static function (): array {
            return [
                'trace_id' => '771a43a4192642f0b136d5159a501700',
                'span_id' => '1234567890abcdef',
            ];
        });

        metrics()->count('test-count', 2, ['foo' => 'bar']);
        metrics()->flush();

        $this->assertCount(1, StubTransport::$events);
        $metric = StubTransport::$events[0]->getMetrics()[0];
        $this->assertSame('771a43a4192642f0b136d5159a501700', (string) $metric->getTraceId());
        $this->assertSame('1234567890abcdef', (string) $metric->getSpanId());

        Scope::clearExternalPropagationContext();
    }
}
