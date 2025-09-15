<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;
use Sentry\Logs\Logs;
use Sentry\Monolog\LogsHandler;
use Sentry\SentrySdk;
use Sentry\State\Hub;

final class LogsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        Logs::getInstance()->flush();
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle($record, Log $expectedLog): void
    {
        $client = ClientBuilder::create([
            'enable_logs' => true,
            'before_send' => static function () {
                return null; // we don't need to send the event, we are just testing the Monolog handler
            },
        ])->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $handler = new LogsHandler();
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();

        $this->assertCount(1, $logs);

        $log = $logs[0];

        $this->assertEquals($expectedLog->getBody(), $log->getBody());
        $this->assertEquals($expectedLog->getLevel(), $log->getLevel());
        $this->assertEquals(
            $expectedLog->attributes()->toSimpleArray(),
            array_filter(
                $log->attributes()->toSimpleArray(),
                static function (string $key) {
                    // We are not testing Sentry's own attributes here, only the ones the user supplied so filter them out of the expected attributes
                    return !str_starts_with($key, 'sentry.');
                },
                \ARRAY_FILTER_USE_KEY
            )
        );
    }

    /**
     * @dataProvider logLevelDataProvider
     */
    public function testLogLevels($record, int $countLogs): void
    {
        $client = ClientBuilder::create([
            'enable_logs' => true,
            'before_send' => static function () {
                return null;
            },
        ])->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $handler = new LogsHandler(LogLevel::warn());
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount($countLogs, $logs);
    }

    public static function handleDataProvider(): iterable
    {
        yield [
            RecordFactory::create(
                'foo bar',
                Logger::DEBUG,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::debug(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::info(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::NOTICE,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::info(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::warn(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::ERROR,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::error(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::CRITICAL,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::ALERT,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::EMERGENCY,
                'channel.foo',
                [],
                []
            ),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
                []
            ),
            (new Log(123, 'abc', LogLevel::info(), 'foo bar'))
                ->setAttribute('foo', 'bar')
                ->setAttribute('bar', 'baz'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [],
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ]
            ),
            (new Log(123, 'abc', LogLevel::info(), 'foo bar'))
                ->setAttribute('foo', 'bar')
                ->setAttribute('bar', 'baz'),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [
                    'foo' => 'bar',
                ],
                [
                    'bar' => 'baz',
                ]
            ),
            (new Log(123, 'abc', LogLevel::info(), 'foo bar'))
                ->setAttribute('foo', 'bar')
                ->setAttribute('bar', 'baz'),
        ];
    }

    public static function logLevelDataProvider(): iterable
    {
        yield [
            RecordFactory::create(
                'foo bar',
                Logger::DEBUG,
                'channel.foo',
                [],
                []
            ),
            0,
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::NOTICE,
                'channel.foo',
                [],
                []
            ),
            0,
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [],
                []
            ),
            0,
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [],
                []
            ),
            1,
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::CRITICAL,
                'channel.foo',
                [],
                []
            ),
            1,
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::ALERT,
                'channel.foo',
                [],
                []
            ),
            1,
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::EMERGENCY,
                'channel.foo',
                [],
                []
            ),
            1,
        ];
    }
}
