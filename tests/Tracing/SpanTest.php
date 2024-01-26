<?php

declare(strict_types=1);

namespace Sentry\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Sentry\Metrics\MetricsUnit;
use Sentry\Metrics\Types\CounterType;
use Sentry\Metrics\Types\DistributionType;
use Sentry\Metrics\Types\GaugeType;
use Sentry\Metrics\Types\SetType;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanId;
use Sentry\Tracing\TraceId;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class SpanTest extends TestCase
{
    /**
     * @dataProvider finishDataProvider
     */
    public function testFinish(?float $currentTimestamp, ?float $endTimestamp, float $expectedEndTimestamp): void
    {
        ClockMock::withClockMock($currentTimestamp);

        $span = new Span();
        $span->finish($endTimestamp);

        $this->assertSame($expectedEndTimestamp, $span->getEndTimestamp());
    }

    public static function finishDataProvider(): iterable
    {
        yield [
            1598660006,
            null,
            1598660006,
        ];

        yield [
            1598660006,
            1598660332,
            1598660332,
        ];
    }

    public function testStartChild(): void
    {
        $spanContext2ParentSpanId = SpanId::generate();
        $spanContext2TraceId = TraceId::generate();

        $spanContext1 = (new SpanContext())
            ->setSampled(false)
            ->setSpanId(SpanId::generate())
            ->setTraceId(TraceId::generate());

        $spanContext2 = SpanContext::make()
            ->setSampled(true)
            ->setParentSpanId($spanContext2ParentSpanId)
            ->setTraceId($spanContext2TraceId);

        $span1 = new Span($spanContext1);
        $span2 = $span1->startChild($spanContext2);

        $this->assertSame($spanContext1->getSampled(), $span1->getSampled());
        $this->assertSame($spanContext1->getSpanId(), $span1->getSpanId());
        $this->assertSame($spanContext1->getTraceId(), $span1->getTraceId());

        $this->assertSame($spanContext1->getSampled(), $span2->getSampled());
        $this->assertSame($spanContext1->getSpanId(), $span2->getParentSpanId());
        $this->assertSame($spanContext1->getTraceId(), $span2->getTraceId());
    }

    /**
     * @dataProvider toTraceparentDataProvider
     */
    public function testToTraceparent(?bool $sampled, string $expectedValue): void
    {
        $span = new Span();
        $span->setSpanId(new SpanId('566e3688a61d4bc8'));
        $span->setTraceId(new TraceId('566e3688a61d4bc888951642d6f14a19'));
        $span->setSampled($sampled);

        $this->assertSame($expectedValue, $span->toTraceparent());
    }

    public static function toTraceparentDataProvider(): iterable
    {
        yield [
            null,
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8',
        ];

        yield [
            false,
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-0',
        ];

        yield [
            true,
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
        ];
    }

    /**
     * @dataProvider toBaggageDataProvider
     */
    public function testToBaggage(string $baggageHeader, string $expectedValue): void
    {
        $context = TransactionContext::fromHeaders(
            '566e3688a61d4bc888951642d6f14a19-566e3688a61d4bc8-1',
            $baggageHeader
        );
        $transaction = new Transaction($context);

        $this->assertSame($expectedValue, $transaction->toBaggage());
    }

    public static function toBaggageDataProvider(): iterable
    {
        yield [
            '',
            '',
        ];

        yield [
            'foo=bar,bar=baz',
            '',
        ];

        yield [
            'sentry-public_key=public,sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1',
            'sentry-public_key=public,sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1',
        ];

        yield [
            'sentry-public_key=public,sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1,foo=bar,bar=baz',
            'sentry-public_key=public,sentry-trace_id=566e3688a61d4bc888951642d6f14a19,sentry-sample_rate=1',
        ];
    }

    public function testMetricsSummary(): void
    {
        $span = new Span();
        $span->setMetricsSummary(
            CounterType::TYPE,
            'counter',
            10,
            MetricsUnit::custom('star'),
            [
                'repository' => 'client',
            ]
        );
        $span->setMetricsSummary(
            CounterType::TYPE,
            'counter',
            50,
            MetricsUnit::custom('star'),
            [
                'repository' => 'client',
            ]
        );
        $span->setMetricsSummary(
            CounterType::TYPE,
            'counter',
            10,
            MetricsUnit::custom('star'),
            [
                'repository' => 'server',
            ]
        );

        $span->setMetricsSummary(
            DistributionType::TYPE,
            'distribution',
            10.2,
            MetricsUnit::millisecond(),
            []
        );
        $span->setMetricsSummary(
            DistributionType::TYPE,
            'distribution',
            5.7,
            MetricsUnit::millisecond(),
            []
        );

        $span->setMetricsSummary(
            GaugeType::TYPE,
            'gauge',
            10,
            MetricsUnit::none(),
            []
        );
        $span->setMetricsSummary(
            GaugeType::TYPE,
            'gauge',
            20,
            MetricsUnit::none(),
            []
        );

        $span->setMetricsSummary(
            SetType::TYPE,
            'set',
            'jane@doe@example.com',
            MetricsUnit::custom('user'),
            []
        );
        $span->setMetricsSummary(
            SetType::TYPE,
            'set',
            'jon@doe@example.com',
            MetricsUnit::custom('user'),
            []
        );

        $this->assertSame([
            'c:counter@star' => [
                'c:counter@stara:1:{s:10:"repository";s:6:"client";}' => [
                    'min' => 10.0,
                    'max' => 50.0,
                    'sum' => 60.0,
                    'count' => 2,
                    'tags' => [
                        'repository' => 'client',
                    ],
                ],
                'c:counter@stara:1:{s:10:"repository";s:6:"server";}' => [
                    'min' => 10.0,
                    'max' => 10.0,
                    'sum' => 10.0,
                    'count' => 1,
                    'tags' => [
                        'repository' => 'server',
                    ],
                ],
            ],
            'd:distribution@millisecond' => [
                'd:distribution@milliseconda:0:{}' => [
                    'min' => 5.7,
                    'max' => 10.2,
                    'sum' => 15.899999999999999,
                    'count' => 2,
                    'tags' => [],
                ],
            ],
            'g:gauge@none' => [
                'g:gauge@nonea:0:{}' => [
                    'min' => 10.0,
                    'max' => 20.0,
                    'sum' => 30.0,
                    'count' => 2,
                    'tags' => [],
                ],
            ],
            's:set@user' => [
                's:set@usera:0:{}' => [
                    'min' => 0.0,
                    'max' => 1.0,
                    'sum' => 1.0,
                    'count' => 2,
                    'tags' => [],
                ],
            ],
        ], $span->getMetricsSummary());
    }
}
