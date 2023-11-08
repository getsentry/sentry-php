<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Metrics\MetricsUnit;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Symfony\Bridge\PhpUnit\ClockMock;

use function Sentry\metrics;

final class MetricsTest extends TestCase
{
    public function testIncr(): void
    {
        ClockMock::withClockMock(1699412953);

        $expectedMetric = [
            'timestamp' => 1699412953,
            'width' => 0,
            'name' => 'c:custom/foo@none',
            'type' => 'c',
            'value' => 10.0,
            'tags' => ['foo' => 'bar'],
        ];

        $event = Event::createMetric();
        $event->setMetric($expectedMetric);

        /** @var HubInterface&MockObject $hub */
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($expectedMetric): bool {
                return $event->getMetric() === $expectedMetric;
            }))
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), metrics()->incr('foo', 10.0, ['foo' => 'bar']));
    }

    public function testDistribution(): void
    {
        ClockMock::withClockMock(1699412953);

        $expectedMetric = [
            'timestamp' => 1699412953,
            'width' => 0,
            'name' => 'd:custom/foo@minute',
            'type' => 'd',
            'value' => [
                10,
                20,
                30,
            ],
            'tags' => ['foo' => 'bar'],
        ];

        $event = Event::createMetric();
        $event->setMetric($expectedMetric);

        /** @var HubInterface&MockObject $hub */
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($expectedMetric): bool {
                return $event->getMetric() === $expectedMetric;
            }))
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), metrics()->distribution('foo', [10, 20, 30], ['foo' => 'bar'], MetricsUnit::minute()));
    }

    public function testSet(): void
    {
        ClockMock::withClockMock(1699412953);

        $expectedMetric = [
            'timestamp' => 1699412953,
            'width' => 0,
            'name' => 's:custom/foo@none',
            'type' => 's',
            'value' => [
                10,
                20,
                30,
            ],
            'tags' => ['foo' => 'bar'],
        ];

        $event = Event::createMetric();
        $event->setMetric($expectedMetric);

        /** @var HubInterface&MockObject $hub */
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($expectedMetric): bool {
                return $event->getMetric() === $expectedMetric;
            }))
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), metrics()->set('foo', [10, 20, 30], ['foo' => 'bar']));
    }

    public function testGauge(): void
    {
        ClockMock::withClockMock(1699412953);

        $expectedMetric = [
            'timestamp' => 1699412953,
            'width' => 0,
            'name' => 'g:custom/foo@none',
            'type' => 'g',
            'value' => 10.0,
            'tags' => ['foo' => 'bar'],
        ];

        $event = Event::createMetric();
        $event->setMetric($expectedMetric);

        /** @var HubInterface&MockObject $hub */
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(static function (Event $event) use ($expectedMetric): bool {
                return $event->getMetric() === $expectedMetric;
            }))
            ->willReturn($event->getId());

        SentrySdk::setCurrentHub($hub);

        $this->assertSame($event->getId(), metrics()->gauge('foo', 10.0, ['foo' => 'bar']));
    }
}
