<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\TestCase;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;

final class LogTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $log = new Log(1.0, '123', LogLevel::debug(), 'foo');

        $this->assertSame(1.0, $log->getTimestamp());
        $this->assertSame('123', $log->getTraceId());
        $this->assertSame(LogLevel::debug(), $log->getLevel());
        $this->assertSame('foo', $log->getBody());
        $this->assertSame([], $log->attributes()->all());

        $log->setTimestamp(2.0);
        $this->assertSame(2.0, $log->getTimestamp());

        $log->setTraceId('456');
        $this->assertSame('456', $log->getTraceId());

        $log->setLevel(LogLevel::warn());
        $this->assertSame(LogLevel::warn(), $log->getLevel());

        $log->setBody('bar');
        $this->assertSame('bar', $log->getBody());
    }

    /**
     * @dataProvider logLevelDataProvider
     */
    public function testLogLevelToPsrMapping(LogLevel $logLevel, $expected): void
    {
        $this->assertSame($expected, $logLevel->toPsrLevel());
    }

    /**
     * @dataProvider logLevelDataProvider
     */
    public function testLogAndLogLevelConsistent(LogLevel $level, $expected): void
    {
        $log = new Log(1.0, '123', $level, 'foo');
        $this->assertSame($expected, $log->getPsrLevel());
    }

    public function logLevelDataProvider(): \Generator
    {
        yield 'Debug -> Debug' => [LogLevel::debug(), \Psr\Log\LogLevel::DEBUG];
        yield 'Trace -> Debug' => [LogLevel::trace(), \Psr\Log\LogLevel::DEBUG];
        yield 'Info -> Info' => [LogLevel::info(), \Psr\Log\LogLevel::INFO];
        yield 'Warn -> Warning' => [LogLevel::warn(), \Psr\Log\LogLevel::WARNING];
        yield 'Error -> Error' => [LogLevel::error(), \Psr\Log\LogLevel::ERROR];
        yield 'Fatal -> Critical' => [LogLevel::fatal(), \Psr\Log\LogLevel::CRITICAL];
    }
}
