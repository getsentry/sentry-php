<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Logs\LogLevel;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

use function Sentry\logger;

final class LogsTest extends TestCase
{
    public function testLogNotSentWhenDisabled(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->any())
               ->method('getOptions')
               ->willReturn(new Options([
                   'dsn' => 'https://public@example.com/1',
                   'enable_logs' => false,
               ]));

        $client->expects($this->never())
               ->method('captureEvent');

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        logger()->info('Some info message');

        $this->assertNull(logger()->flush());
    }

    public function testLogSentWhenEnabled(): void
    {
        $this->assertEvent(function (Event $event) {
            $this->assertCount(1, $event->getLogs());

            $logItem = $event->getLogs()[0]->jsonSerialize();

            $this->assertEquals(LogLevel::info(), $logItem['level']);
            $this->assertEquals('Some info message', $logItem['body']);
        });

        logger()->info('Some info message');

        $this->assertNotNull(logger()->flush());
    }

    public function testLogWithTemplate(): void
    {
        $this->assertEvent(function (Event $event) {
            $this->assertCount(1, $event->getLogs());

            $logItem = $event->getLogs()[0]->jsonSerialize();

            $this->assertEquals(LogLevel::info(), $logItem['level']);
            $this->assertEquals('Some info message', $logItem['body']);
        });

        logger()->info('Some %s message', ['info']);

        $this->assertNotNull(logger()->flush());
    }

    public function testLogWithNestedAttributes(): void
    {
        $this->assertEvent(function (Event $event) {
            $this->assertCount(1, $event->getLogs());

            $logItem = $event->getLogs()[0]->jsonSerialize();

            $this->assertArrayHasKey('nested.foo', $logItem['attributes']);
            $this->assertArrayNotHasKey('nested.should-be-missing', $logItem['attributes']);

            $this->assertEquals('bar', $logItem['attributes']['nested.foo']['value']);
        });

        logger()->info('Some message', [], [
            'nested' => [
                'foo' => 'bar',
                'should-be-missing' => [1, 2, 3],
            ],
        ]);

        $this->assertNotNull(logger()->flush());
    }

    /**
     * @dataProvider logLevelDataProvider
     */
    public function testLoggerSetsCorrectLevel(LogLevel $level): void
    {
        $this->assertEvent(function (Event $event) use ($level) {
            $this->assertCount(1, $event->getLogs());

            $this->assertEquals($level, $event->getLogs()[0]->getLevel());
        });

        logger()->{(string) $level}('Some message');

        $this->assertNotNull(logger()->flush());
    }

    public static function logLevelDataProvider(): \Generator
    {
        yield [LogLevel::trace()];
        yield [LogLevel::debug()];
        yield [LogLevel::info()];
        yield [LogLevel::warn()];
        yield [LogLevel::error()];
        yield [LogLevel::fatal()];
    }

    /**
     * @param callable(Event): void $assert
     */
    private function assertEvent(callable $assert): ClientInterface
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
                  ->method('send')
                  ->with($this->callback(function (Event $event) use ($assert): bool {
                      $assert($event);

                      return true;
                  }))
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create([
            'enable_logs' => true,
        ])->setTransport($transport)->getClient();

        $hub = new Hub($client);
        SentrySdk::setCurrentHub($hub);

        return $client;
    }
}
