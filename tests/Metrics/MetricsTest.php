<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Metrics\MetricsUnit;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Metrics\Types\SetType;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
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
            ]));

        $self = $this;

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($self): bool {
                $metric = $event->getMetrics()[1693069352];

                $self->assertSame(CounterType::TYPE, $metric->getType());
                $self->assertSame('foo', $metric->getKey());
                $self->assertSame([3.0], $metric->serialize());
                $self->assertSame(MetricsUnit::second(), $metric->getUnit());
                $self->assertSame(
                    [
                        'environment' => 'development',
                        'foo' => 'bar',
                        'release' => '1.0.0',
                    ],
                    $metric->getTags()
                );
                $self->assertSame(1699412953, $metric->getTimestamp());

                $codeLocation = $metric->getCodeLocation();

                $self->assertSame('Sentry\Metrics\Metrics::increment', $codeLocation->getFunctionName());

                return true;
            }));

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
            ]));

        $self = $this;

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($self): bool {
                $metric = $event->getMetrics()[1924320516];

                $self->assertSame(DistributionType::TYPE, $metric->getType());
                $self->assertSame('foo', $metric->getKey());
                $self->assertSame([1.0, 2.0], $metric->serialize());
                $self->assertSame(MetricsUnit::second(), $metric->getUnit());
                $self->assertSame(
                    [
                        'environment' => 'development',
                        'foo' => 'bar',
                        'release' => '1.0.0',
                    ],
                    $metric->getTags()
                );
                $self->assertSame(1699412953, $metric->getTimestamp());

                $codeLocation = $metric->getCodeLocation();

                $self->assertSame('Sentry\Metrics\Metrics::distribution', $codeLocation->getFunctionName());

                return true;
            }));

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
            ]));

        $self = $this;

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($self): bool {
                $metric = $event->getMetrics()[1062915427];

                $self->assertSame(GaugeType::TYPE, $metric->getType());
                $self->assertSame('foo', $metric->getKey());
                $self->assertSame([
                    2.0, // last
                    1.0, // min
                    2.0, // max
                    3.0, // sum,
                    2, // count,
                ], $metric->serialize());
                $self->assertSame(MetricsUnit::second(), $metric->getUnit());
                $self->assertSame(
                    [
                        'environment' => 'development',
                        'foo' => 'bar',
                        'release' => '1.0.0',
                    ],
                    $metric->getTags()
                );
                $self->assertSame(1699412953, $metric->getTimestamp());

                $codeLocation = $metric->getCodeLocation();

                $self->assertSame('Sentry\Metrics\Metrics::gauge', $codeLocation->getFunctionName());

                return true;
            }));

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
            ]));

        $self = $this;

        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($self): bool {
                $metric = $event->getMetrics()[3512255301];

                $self->assertSame(SetType::TYPE, $metric->getType());
                $self->assertSame('foo', $metric->getKey());
                $self->assertSame([1, 1, 2356372769], $metric->serialize());
                $self->assertSame(MetricsUnit::second(), $metric->getUnit());
                $self->assertSame(
                    [
                        'environment' => 'development',
                        'foo' => 'bar',
                        'release' => '1.0.0',
                    ],
                    $metric->getTags()
                );
                $self->assertSame(1699412953, $metric->getTimestamp());

                $codeLocation = $metric->getCodeLocation();

                $self->assertSame('Sentry\Metrics\Metrics::set', $codeLocation->getFunctionName());

                return true;
            }));

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
}
