<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\Logs\LogLevel;
use Sentry\Logs\LogsAggregator;
use Sentry\Monolog\LogsHandler;
use Sentry\SentrySdk;
use Sentry\State\Hub;

final class LogsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up a client with logs enabled for testing
        $client = ClientBuilder::create([
            'enable_logs' => true,
        ])->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);
    }

    public function testHandlerCreatesDefaultAggregatorWhenNoneProvided(): void
    {
        $handler = new LogsHandler();
        
        $this->assertInstanceOf(LogsAggregator::class, $handler->getLogsAggregator());
    }

    public function testHandlerUsesProvidedAggregator(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator);
        
        $this->assertSame($aggregator, $handler->getLogsAggregator());
    }

    public function testHandlerMapsMonologLevelsCorrectly(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator);

        $testCases = [
            [Logger::DEBUG, LogLevel::debug()],
            [Logger::INFO, LogLevel::info()],
            [Logger::NOTICE, LogLevel::info()],
            [Logger::WARNING, LogLevel::warn()],
            [Logger::ERROR, LogLevel::error()],
            [Logger::CRITICAL, LogLevel::fatal()],
            [Logger::ALERT, LogLevel::fatal()],
            [Logger::EMERGENCY, LogLevel::fatal()],
        ];

        foreach ($testCases as [$monologLevel, $expectedSentryLevel]) {
            $record = RecordFactory::create('Test message', $monologLevel, 'test', [], []);
            $handler->handle($record);
            
            // Verify the log was added by checking the aggregator's logs
            $logs = $aggregator->all();
            $this->assertCount(1, $logs, "Expected exactly one log for level {$monologLevel}");
            
            $log = $logs[0];
            $this->assertEquals((string) $expectedSentryLevel, (string) $log->getLevel());
            $this->assertEquals('Test message', $log->getBody());
            
            // Clear logs for next iteration
            $aggregator->flush();
        }
    }

    public function testHandlerIncludesMonologDataAsAttributes(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator, Logger::DEBUG, true, true);

        $context = ['user_id' => 123, 'action' => 'login'];
        $extra = ['ip' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'];

        $record = RecordFactory::create('Test message', Logger::INFO, 'test_channel', $context, $extra);
        $handler->handle($record);
        
        $logs = $aggregator->all();
        $this->assertCount(1, $logs);
        
        $log = $logs[0];
        $this->assertEquals('Test message', $log->getBody());
        $this->assertEquals((string) LogLevel::info(), (string) $log->getLevel());
        
        $attributes = $log->attributes()->toSimpleArray();
        $this->assertArrayHasKey('monolog.channel', $attributes);
        $this->assertArrayHasKey('monolog.level_name', $attributes);
        $this->assertArrayHasKey('monolog.context.user_id', $attributes);
        $this->assertArrayHasKey('monolog.context.action', $attributes);
        $this->assertArrayHasKey('monolog.extra.ip', $attributes);
        $this->assertArrayHasKey('monolog.extra.user_agent', $attributes);
        
        $this->assertEquals('test_channel', $attributes['monolog.channel']);
        $this->assertEquals('INFO', $attributes['monolog.level_name']);
        $this->assertEquals(123, $attributes['monolog.context.user_id']);
        $this->assertEquals('login', $attributes['monolog.context.action']);
        $this->assertEquals('192.168.1.1', $attributes['monolog.extra.ip']);
        $this->assertEquals('Mozilla/5.0', $attributes['monolog.extra.user_agent']);
    }

    public function testHandlerExcludesMonologDataWhenDisabled(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator, Logger::DEBUG, true, false);

        $context = ['user_id' => 123];
        $extra = ['ip' => '192.168.1.1'];

        $record = RecordFactory::create('Test message', Logger::INFO, 'test_channel', $context, $extra);
        $handler->handle($record);
        
        $logs = $aggregator->all();
        $this->assertCount(1, $logs);
        
        $log = $logs[0];
        $this->assertEquals('Test message', $log->getBody());
        
        // When includeMonologData is false, there should be no monolog.* attributes
        $attributes = $log->attributes()->toSimpleArray();
        $monologAttributes = array_filter($attributes, function ($key) {
            return str_starts_with($key, 'monolog.');
        }, ARRAY_FILTER_USE_KEY);
        
        $this->assertEmpty($monologAttributes, 'No Monolog attributes should be present when disabled');
    }

    public function testHandlerHandlesExceptionInContext(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator, Logger::DEBUG, true, true);

        $exception = new \RuntimeException('Test exception', 100);
        $context = ['exception' => $exception, 'other' => 'value'];

        $record = RecordFactory::create('Test message', Logger::ERROR, 'test_channel', $context, []);
        $handler->handle($record);
        
        $logs = $aggregator->all();
        $this->assertCount(1, $logs);
        
        $log = $logs[0];
        $attributes = $log->attributes()->toSimpleArray();
        
        $this->assertEquals(\RuntimeException::class, $attributes['monolog.context.exception.class']);
        $this->assertEquals('Test exception', $attributes['monolog.context.exception.message']);
        $this->assertEquals($exception->getFile(), $attributes['monolog.context.exception.file']);
        $this->assertEquals($exception->getLine(), $attributes['monolog.context.exception.line']);
        $this->assertEquals('value', $attributes['monolog.context.other']);
    }

    public function testFlushCallsAggregatorFlush(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator);

        // Add a log first
        $record = RecordFactory::create('Test message', Logger::INFO, 'test', [], []);
        $handler->handle($record);
        
        // Verify log exists
        $this->assertCount(1, $aggregator->all());
        
        // Flush should clear the logs
        $handler->flush();
        $this->assertCount(0, $aggregator->all());
    }

    public function testHandlerRespectsMinimumLevel(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator, Logger::WARNING);

        // Should not handle DEBUG level
        $record = RecordFactory::create('Debug message', Logger::DEBUG, 'test', [], []);
        $result = $handler->handle($record);
        
        $this->assertFalse($result);
        $this->assertCount(0, $aggregator->all(), 'No logs should be added for levels below threshold');
    }

    public function testHandlerHandlesMinimumLevelAndAbove(): void
    {
        $aggregator = new LogsAggregator();
        $handler = new LogsHandler($aggregator, Logger::WARNING, false); // Set bubble to false

        // Should handle WARNING level
        $record = RecordFactory::create('Warning message', Logger::WARNING, 'test', [], []);
        $result = $handler->handle($record);
        
        $this->assertTrue($result);
        $this->assertCount(1, $aggregator->all(), 'One log should be added for levels at or above threshold');
    }
} 