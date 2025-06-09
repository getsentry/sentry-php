<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Logs\LogLevel;
use Sentry\Logs\LogsAggregator;
use Sentry\SentrySdk;
use Sentry\State\Hub;

final class LogsAggregatorTest extends TestCase
{
    /**
     * @dataProvider messageFormattingDataProvider
     */
    public function testMessageFormatting(string $message, array $values, string $expected): void
    {
        $client = ClientBuilder::create([
            'enable_logs' => true,
        ])->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $aggregator = new LogsAggregator();

        $aggregator->add(LogLevel::info(), $message, $values);

        $logs = $aggregator->all();

        $this->assertCount(1, $logs);

        $log = $logs[0];

        $this->assertEquals($expected, $log->getBody());
    }

    public static function messageFormattingDataProvider(): \Generator
    {
        yield [
            'Simple message without values',
            [],
            'Simple message without values',
        ];

        yield [
            'Message with a value: %s',
            ['value'],
            'Message with a value: value',
        ];

        yield [
            'Message with placeholders but no values: %s',
            [],
            'Message with placeholders but no values: %s',
        ];

        yield [
            'Message with placeholders but incorrect number of values: %s, %s',
            ['value'],
            'Message with placeholders but incorrect number of values: %s, %s',
        ];
    }
}
