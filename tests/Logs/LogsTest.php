<?php

declare(strict_types=1);

namespace Sentry\Tests\Logs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Logs\Log;
use Sentry\Logs\LogLevel;
use Sentry\Options;
use Sentry\SentrySdk;
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

        SentrySdk::init($client);

        logger()->info('Some info message');

        $this->assertNull(logger()->flush());
    }

    public function testLogSentWhenEnabled(): void
    {
        $this->assertEvent(function (Event $event) {
            $this->assertCount(1, $event->getLogs());

            $logItem = $event->getLogs()[0];

            $this->assertEquals(LogLevel::info(), $logItem->getLevel());
            $this->assertEquals('Some info message', $logItem->getBody());
        });

        logger()->info('Some info message');

        $this->assertNotNull(logger()->flush());
    }

    public function testLogNotSentWithBeforeSendLogOption(): void
    {
        $this->assertEvent(
            function (Event $event) {
                $this->assertCount(1, $event->getLogs());

                $logItem = $event->getLogs()[0];

                $this->assertEquals(LogLevel::fatal(), $logItem->getLevel());
                $this->assertEquals('Some test message', $logItem->getBody());
            },
            [
                'before_send_log' => static function (Log $log): ?Log {
                    if ($log->getLevel() === LogLevel::info()) {
                        // Returning null will prevent the log from being sent
                        return null;
                    }

                    // Return the log while changing the level to fatal
                    return $log->setLevel(LogLevel::fatal());
                },
            ]
        );

        logger()->info('Some info message');
        logger()->warn('Some test message');

        $this->assertNotNull(logger()->flush());
    }

    public function testLogWithTemplate(): void
    {
        $this->assertEvent(function (Event $event) {
            $this->assertCount(1, $event->getLogs());

            $logItem = $event->getLogs()[0];

            $this->assertEquals(LogLevel::info(), $logItem->getLevel());
            $this->assertEquals('Some info message', $logItem->getBody());
        });

        logger()->info('Some %s message', ['info']);

        $this->assertNotNull(logger()->flush());
    }

    public function testLogWithNestedAttributes(): void
    {
        $this->assertEvent(function (Event $event) {
            $this->assertCount(1, $event->getLogs());

            $logItem = $event->getLogs()[0];

            $this->assertNull($logItem->attributes()->get('nested.should-be-missing'));

            $attribute = $logItem->attributes()->get('nested.foo');

            $this->assertNotNull($attribute);
            $this->assertEquals('bar', $attribute->getValue());

            $attribute = $logItem->attributes()->get('nested.baz');

            $this->assertNotNull($attribute);
            $this->assertEquals(json_encode([1, 2, 3]), $attribute->getValue());
        });

        logger()->info('Some message', [], [
            'nested' => [
                'foo' => 'bar',
                'baz' => [1, 2, 3],
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
    private function assertEvent(callable $assert, array $options = []): ClientInterface
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

        $clientOptions = array_merge([
            'enable_logs' => true,
        ], $options);

        $client = ClientBuilder::create($clientOptions)->setTransport($transport)->getClient();

        SentrySdk::init($client);

        return $client;
    }
}
