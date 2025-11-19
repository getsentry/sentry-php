<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Metrics\MetricsAggregator;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Metrics\Unit;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubAdapter;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Sentry\Util\ClockMock;

use function Sentry\metrics;

final class MetricsTest extends TestCase
{
    public function testIncrement(): void
    {
        ClockMock::withClockMock(1699412953);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
            ->method('getOptions')
            ->willReturn(new Options([
                'release' => '1.0.0',
                'environment' => 'development',
                'attach_metric_code_locations' => true,
            ]));

        $self = $this;

        $client->expects($this->never())
            ->method('captureEvent');

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        metrics()->increment(
            'foo',
            1,
            Unit::second(),
            ['foo' => 'bar']
        );

        metrics()->increment(
            'foo',
            2,
            Unit::second(),
            ['foo' => 'bar']
        );

        metrics()->flush();
    }

    public function testTiming(): void
    {
        ClockMock::withClockMock(1699412953);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
               ->method('getOptions')
               ->willReturn(new Options([
                   'release' => '1.0.0',
                   'environment' => 'development',
                   'attach_metric_code_locations' => true,
               ]));

        $self = $this;

        $client->expects($this->never())
            ->method('captureEvent');

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $firstTimingResult = metrics()->timing(
            'foo',
            static function () {
                // Move the clock forward 1 second
                ClockMock::withClockMock(1699412954);

                return '1second';
            },
            ['foo' => 'bar']
        );

        $this->assertEquals('1second', $firstTimingResult);

        ClockMock::withClockMock(1699412953);

        $secondTimingResult = metrics()->timing(
            'foo',
            static function () {
                // Move the clock forward 2 seconds
                ClockMock::withClockMock(1699412955);
            },
            ['foo' => 'bar']
        );

        $this->assertNull($secondTimingResult);

        metrics()->flush();
    }

    public function testSet(): void
    {
        ClockMock::withClockMock(1699412953);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
            ->method('getOptions')
            ->willReturn(new Options([
                'release' => '1.0.0',
                'environment' => 'development',
                'attach_metric_code_locations' => true,
            ]));

        $self = $this;

        $client->expects($this->never())
            ->method('captureEvent');

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        metrics()->set(
            'foo',
            1,
            Unit::second(),
            ['foo' => 'bar']
        );

        metrics()->set(
            'foo',
            1,
            Unit::second(),
            ['foo' => 'bar']
        );

        metrics()->set(
            'foo',
            'foo',
            Unit::second(),
            ['foo' => 'bar']
        );

        metrics()->flush();
    }

    public function testMetricsSummary(): void
    {
        ClockMock::withClockMock(1699412953);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
            ->method('getOptions')
            ->willReturn(new Options([
                'enable_tracing' => true,
                'environment' => 'development',
                'release' => '1.0.0',
            ]));

        $self = $this;

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($self): bool {
                $self->assertSame(
                    [],
                    $event->getMetricsSummary()
                );

                $self->assertSame(
                    [],
                    $event->getSpans()[0]->getMetricsSummary()
                );

                return true;
            }));

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $transactionContext = TransactionContext::make()
            ->setName('GET /metrics')
            ->setOp('http.server');
        $transaction = $hub->startTransaction($transactionContext);
        $hub->setSpan($transaction);

        metrics()->increment(
            'foo',
            1,
            Unit::second(),
            ['foo' => 'bar']
        );

        $spanContext = SpanContext::make()
            ->setOp('function');
        $span = $transaction->startChild($spanContext);
        $hub->setSpan($span);

        metrics()->increment(
            'foo',
            1,
            Unit::second(),
            ['foo' => 'bar']
        );

        metrics()->increment(
            'foo',
            1,
            Unit::second(),
            ['foo' => 'bar']
        );

        $span->finish();
        $transaction->finish();
    }

    // ======= Metrics V2 =========

    protected function setUp(): void
    {
        HubAdapter::getInstance()->bindClient(new Client(new Options(), new StubTransport()));
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
        $this->assertEquals(CounterType::TYPE, $metric->getType());
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
        $this->assertEquals(GaugeType::TYPE, $metric->getType());
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
        $this->assertEquals(DistributionType::TYPE, $metric->getType());
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
}

class StubTransport implements TransportInterface
{
    /**
     * @var Event[]
     */
    public static $events = [];

    public function send(Event $event): Result
    {
        self::$events[] = $event;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
