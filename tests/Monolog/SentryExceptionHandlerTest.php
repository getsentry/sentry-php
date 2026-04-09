<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Monolog\SentryExceptionHandler;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class SentryExceptionHandlerTest extends TestCase
{
    /**
     * @dataProvider capturedRecordsDataProvider
     *
     * @param LogRecord|array<string, mixed> $record
     * @param array<string, mixed>           $expectedExtra
     */
    public function testHandleCapturesExceptionAndAddsMetadata($record, \Throwable $exception, array $expectedExtra): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with(
                $this->identicalTo($exception),
                $this->callback(function (Scope $scopeArg) use ($expectedExtra): bool {
                    $event = $scopeArg->applyToEvent(Event::createEvent());

                    $this->assertNotNull($event);
                    $this->assertSame($expectedExtra, $event->getExtra());

                    return true;
                }),
                null
            );

        $handler = new SentryExceptionHandler(new Hub($client, new Scope()));

        $this->assertTrue($handler->isHandling($record));
        $handler->handle($record);
    }

    public function testHandleReturnsFalseWhenBubblingEnabled(): void
    {
        $exception = new \RuntimeException('boom');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($this->identicalTo($exception), $this->isInstanceOf(Scope::class), null);

        $handler = new SentryExceptionHandler(new Hub($client, new Scope()), Logger::WARNING);
        $record = RecordFactory::create(
            'foo bar',
            Logger::WARNING,
            'channel.foo',
            [
                'exception' => $exception,
            ],
            []
        );

        $this->assertTrue($handler->isHandling($record));
        $this->assertFalse($handler->handle($record));
    }

    public function testHandleReturnsTrueWhenBubblingDisabled(): void
    {
        $exception = new \RuntimeException('boom');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureException')
            ->with($this->identicalTo($exception), $this->isInstanceOf(Scope::class), null);

        $handler = new SentryExceptionHandler(new Hub($client, new Scope()), Logger::WARNING, false);
        $record = RecordFactory::create(
            'foo bar',
            Logger::WARNING,
            'channel.foo',
            [
                'exception' => $exception,
            ],
            []
        );

        $this->assertTrue($handler->isHandling($record));
        $this->assertTrue($handler->handle($record));
    }

    /**
     * @dataProvider ignoredRecordsDataProvider
     *
     * @param LogRecord|array<string, mixed> $record
     */
    public function testHandleIgnoresRecordsWithoutThrowable($record): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())
            ->method('captureException');

        $handler = new SentryExceptionHandler(new Hub($client, new Scope()), Logger::DEBUG, false);

        $this->assertTrue($handler->isHandling($record));
        $this->assertFalse($handler->handle($record));
    }

    public function testHandleIgnoresRecordsBelowThreshold(): void
    {
        $exception = new \RuntimeException('boom');

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())
            ->method('captureException');

        $handler = new SentryExceptionHandler(new Hub($client, new Scope()), Logger::ERROR, false);
        $record = RecordFactory::create(
            'foo bar',
            Logger::WARNING,
            'channel.foo',
            [
                'exception' => $exception,
            ],
            []
        );

        $this->assertFalse($handler->isHandling($record));
        $this->assertFalse($handler->handle($record));
    }

    public function testLegacyIsHandlingUsesMinimalLevelRecord(): void
    {
        if (Logger::API >= 3) {
            $this->markTestSkipped('Test only works for Monolog < 3');
        }

        $handler = new SentryExceptionHandler(new Hub($this->createMock(ClientInterface::class), new Scope()), Logger::WARNING);

        $this->assertTrue($handler->isHandling(['level' => Logger::WARNING]));
        $this->assertFalse($handler->isHandling(['level' => Logger::INFO]));
    }

    /**
     * @return iterable<array{LogRecord|array<string, mixed>}>
     */
    public static function ignoredRecordsDataProvider(): iterable
    {
        yield [
            RecordFactory::create('foo bar', Logger::WARNING, 'channel.foo', [], []),
        ];

        yield [
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'exception' => 'not an exception',
                ],
                []
            ),
        ];
    }

    /**
     * @return iterable<array{LogRecord|array<string, mixed>, \Throwable, array<string, mixed>}>
     */
    public static function capturedRecordsDataProvider(): iterable
    {
        $exception = new \RuntimeException('exception message');

        yield 'with exception only' => [
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'exception' => $exception,
                ],
                []
            ),
            $exception,
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
                'monolog.message' => 'foo bar',
            ],
        ];

        $exception = new \RuntimeException('exception message');

        yield 'with context and extra' => [
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'exception' => $exception,
                    'foo' => 'bar',
                ],
                [
                    'bar' => 'baz',
                ]
            ),
            $exception,
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
                'monolog.message' => 'foo bar',
                'monolog.context' => [
                    'foo' => 'bar',
                ],
                'monolog.extra' => [
                    'bar' => 'baz',
                ],
            ],
        ];
    }
}
