<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Metrics\MetricsUnit;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Symfony\Bridge\PhpUnit\ClockMock;

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
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->increment(
            'foo',
            2,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->flush();
    }

    public function testDistribution(): void
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

        metrics()->distribution(
            'foo',
            1,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->distribution(
            'foo',
            2,
            MetricsUnit::second(),
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

    public function testGauge(): void
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

        metrics()->gauge(
            'foo',
            1,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->gauge(
            'foo',
            2,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

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
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->set(
            'foo',
            1,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->set(
            'foo',
            'foo',
            MetricsUnit::second(),
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
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        $spanContext = SpanContext::make()
            ->setOp('function');
        $span = $transaction->startChild($spanContext);
        $hub->setSpan($span);

        metrics()->increment(
            'foo',
            1,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        metrics()->increment(
            'foo',
            1,
            MetricsUnit::second(),
            ['foo' => 'bar']
        );

        $span->finish();
        $transaction->finish();
    }
}
