<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;
use Sentry\Logs\Logs;
use Sentry\Monolog\LogsHandler;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Tests\StubTransport;

final class LogsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        Logs::getInstance()->flush();
        $client = ClientBuilder::create([
            'enable_logs' => true,
            'before_send' => static function () {
                return null; // we don't need to send the event, we are just testing the Monolog handler
            },
        ])->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle($record, Log $expectedLog): void
    {
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
        $handler = new LogsHandler(LogLevel::warn());
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount($countLogs, $logs);
    }

    /**
     * @dataProvider logLevelDataProvider
     */
    public function testLogLevelsMonologEnum($record, int $countLogs): void
    {
        if (Logger::API < 3) {
            $this->markTestSkipped('Test only works for Monolog >= 3');
        }
        $handler = new LogsHandler(Level::Warning);
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount($countLogs, $logs);
    }

    /**
     * @dataProvider logLevelDataProvider
     */
    public function testLogLevelsLegacyMonolog($record, int $countLogs): void
    {
        $handler = new LogsHandler(Logger::WARNING);
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount($countLogs, $logs);
    }

    /**
     * @dataProvider monologLegacyLevelDataProvider
     */
    public function testFilterOnMonologLevels(int $level, $record, ?Log $log = null): void
    {
        $handler = new LogsHandler($level);
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();
        if ($log === null) {
            $this->assertEmpty($logs);
        } else {
            $this->assertNotEmpty($logs);
            $this->assertEquals($log->getLevel(), $logs[0]->getLevel());
        }
    }

    /**
     * @param Level $level
     *
     * @dataProvider monologLevelDataProvider
     */
    public function testFilterOnNewMonologLevels($level, $record, ?Log $log = null): void
    {
        if (!class_exists(Level::class)) {
            $this->markTestSkipped('Test only works for Monolog >= 3');
        }
        $handler = new LogsHandler($level);
        $handler->handle($record);

        $logs = Logs::getInstance()->aggregator()->all();
        if ($log === null) {
            $this->assertEmpty($logs);
        } else {
            $this->assertNotEmpty($logs);
            $this->assertEquals($log->getLevel(), $logs[0]->getLevel());
        }
    }

    public function testLogsHandlerDestructor()
    {
        $transport = new StubTransport();
        $client = ClientBuilder::create([
            'enable_logs' => true,
        ])->setTransport($transport)
            ->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        $this->handleLogAndDrop();

        $this->assertCount(1, StubTransport::$events);
        $this->assertSame('I was dropped :(', StubTransport::$events[0]->getLogs()[0]->getBody());
    }

    private function handleLogAndDrop(): void
    {
        $handler = new LogsHandler();
        $handler->handle(RecordFactory::create('I was dropped :(', Logger::INFO, 'chanel.foo', [], []));
    }

    public function testOriginTagAppliedWithHandler(): void
    {
        $handler = new LogsHandler(LogLevel::warn());
        $handler->handle(RecordFactory::create('with origin', Logger::WARNING, 'channel.foo', [], []));

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertArrayHasKey('sentry.origin', $log->attributes()->toSimpleArray());
        $this->assertSame('auto.log.monolog', $log->attributes()->toSimpleArray()['sentry.origin']);
    }

    public function testOriginTagNotAppliedWhenUsingDirectly()
    {
        \Sentry\logger()->info('No origin attribute');

        $logs = Logs::getInstance()->aggregator()->all();
        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertSame('No origin attribute', $log->getBody());
        $this->assertArrayNotHasKey('sentry.origin', $log->attributes()->toSimpleArray());
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

    public static function monologLegacyLevelDataProvider(): iterable
    {
        yield [
            Logger::NOTICE,
            RecordFactory::create(
                'foo bar',
                Logger::NOTICE,
                'channel.foo'
            ),
            new Log(123, 'abc', LogLevel::info(), 'foo bar'),
        ];

        yield [
            Logger::NOTICE,
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo'
            ),
            null,
        ];

        yield 'Warnings are passed through if Notice is configured' => [
            Logger::NOTICE,
            RecordFactory::create('foo bar', Logger::WARNING, 'channel.foo'),
            new Log(123, 'abc', LogLevel::warn(), 'foo bar'),
        ];

        yield 'Filter out critical even though both convert to Sentry Fatal' => [
            Logger::ALERT,
            RecordFactory::create('foo bar', Logger::CRITICAL, 'channel.foo'),
            null,
        ];

        yield [
            Logger::ALERT,
            RecordFactory::create('foo bar', Logger::EMERGENCY, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            Logger::ALERT,
            RecordFactory::create('foo bar', Logger::ALERT, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield 'Emergency is passed through if Alert is configured (both are sentry fatal)' => [
            Logger::ALERT,
            RecordFactory::create('foo bar', Logger::EMERGENCY, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield 'Alert is filtered when emergency is configured (both are sentry fatal)' => [
            Logger::EMERGENCY,
            RecordFactory::create('foo bar', Logger::ALERT, 'channel.foo'),
            null,
        ];

        yield [
            Logger::EMERGENCY,
            RecordFactory::create('foo bar', Logger::EMERGENCY, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];
    }

    public static function monologLevelDataProvider(): iterable
    {
        yield [
            Level::Notice,
            RecordFactory::create(
                'foo bar',
                Logger::NOTICE,
                'channel.foo'
            ),
            new Log(123, 'abc', LogLevel::info(), 'foo bar'),
        ];

        yield [
            Level::Notice,
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo'
            ),
            null,
        ];

        yield 'Warnings are passed through if Notice is configured' => [
            Level::Notice,
            RecordFactory::create('foo bar', Logger::WARNING, 'channel.foo'),
            new Log(123, 'abc', LogLevel::warn(), 'foo bar'),
        ];

        yield 'Filter out critical even though both convert to Sentry Fatal' => [
            Level::Alert,
            RecordFactory::create('foo bar', Logger::CRITICAL, 'channel.foo'),
            null,
        ];

        yield [
            Level::Alert,
            RecordFactory::create('foo bar', Logger::EMERGENCY, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield [
            Level::Alert,
            RecordFactory::create('foo bar', Logger::ALERT, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield 'Emergency is passed through if Alert is configured (both are sentry fatal)' => [
            Level::Alert,
            RecordFactory::create('foo bar', Logger::EMERGENCY, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];

        yield 'Alert is filtered when emergency is configured (both are sentry fatal)' => [
            Level::Emergency,
            RecordFactory::create('foo bar', Logger::ALERT, 'channel.foo'),
            null,
        ];

        yield [
            Level::Emergency,
            RecordFactory::create('foo bar', Logger::EMERGENCY, 'channel.foo'),
            new Log(123, 'abc', LogLevel::fatal(), 'foo bar'),
        ];
    }
}
