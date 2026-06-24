<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Monolog\ExceptionToSentryIssueHandler;
use Sentry\Monolog\LogToSentryIssueHandler;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\MergedScope;
use Sentry\Tests\StubTransport;

final class LogToSentryIssueHandlerTest extends TestCase
{
    /**
     * @dataProvider capturedRecordsDataProvider
     *
     * @param LogRecord|array<string, mixed> $record
     * @param array<string, mixed>           $expectedExtra
     */
    public function testHandleCapturesLogMessageAsIssue(bool $fillExtraContext, $record, Severity $expectedSeverity, array $expectedExtra): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(
                $this->callback(function (Event $event) use ($expectedSeverity): bool {
                    $this->assertEquals($expectedSeverity, $event->getLevel());
                    $this->assertSame('foo bar', $event->getMessage());
                    $this->assertSame('monolog.channel.foo', $event->getLogger());

                    return true;
                }),
                $this->callback(function (EventHint $hint): bool {
                    $this->assertNull($hint->exception);
                    $this->assertNull($hint->mechanism);
                    $this->assertNull($hint->stacktrace);
                    $this->assertSame([], $hint->extra);

                    return true;
                }),
                $this->callback(function (MergedScope $scopeArg) use ($expectedExtra): bool {
                    $event = $scopeArg->applyToEvent(Event::createEvent());

                    $this->assertNotNull($event);
                    $this->assertSame($expectedExtra, $event->getExtra());

                    return true;
                })
            );

        SentrySdk::init($client);

        $handler = new LogToSentryIssueHandler(Logger::DEBUG, true, $fillExtraContext);

        $this->assertTrue($handler->isHandling($record));
        $this->assertFalse($handler->handle($record));
    }

    public function testHandleReturnsTrueWhenBubblingDisabled(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->isInstanceOf(Event::class), $this->isInstanceOf(EventHint::class), $this->isInstanceOf(MergedScope::class));

        SentrySdk::init($client);

        $handler = new LogToSentryIssueHandler(Logger::WARNING, false);
        $record = RecordFactory::create('foo bar', Logger::WARNING, 'channel.foo', [], []);

        $this->assertTrue($handler->isHandling($record));
        $this->assertTrue($handler->handle($record));
    }

    public function testHandleIgnoresRecordsWithThrowableExceptionContext(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())
            ->method('captureEvent');

        SentrySdk::init($client);

        $handler = new LogToSentryIssueHandler(Logger::DEBUG, false);
        $record = RecordFactory::create(
            'foo bar',
            Logger::WARNING,
            'channel.foo',
            [
                'exception' => new \RuntimeException('boom'),
            ],
            []
        );

        $this->assertTrue($handler->isHandling($record));
        $this->assertFalse($handler->handle($record));
    }

    public function testHandleCapturesRecordsWithNonThrowableExceptionContext(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(
                $this->isInstanceOf(Event::class),
                $this->isInstanceOf(EventHint::class),
                $this->callback(function (MergedScope $scopeArg): bool {
                    $event = $scopeArg->applyToEvent(Event::createEvent());

                    $this->assertNotNull($event);
                    $this->assertSame([
                        'monolog.channel' => 'channel.foo',
                        'monolog.level' => Logger::getLevelName(Logger::WARNING),
                        'monolog.context' => [
                            'exception' => 'not an exception',
                        ],
                    ], $event->getExtra());

                    return true;
                })
            );

        SentrySdk::init($client);

        $handler = new LogToSentryIssueHandler(Logger::DEBUG, false, true);
        $record = RecordFactory::create(
            'foo bar',
            Logger::WARNING,
            'channel.foo',
            [
                'exception' => 'not an exception',
            ],
            []
        );

        $this->assertTrue($handler->isHandling($record));
        $this->assertTrue($handler->handle($record));
    }

    public function testHandleIgnoresRecordsBelowThreshold(): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->never())
            ->method('captureEvent');

        SentrySdk::init($client);

        $handler = new LogToSentryIssueHandler(Logger::ERROR, false);
        $record = RecordFactory::create('foo bar', Logger::WARNING, 'channel.foo', [], []);

        $this->assertFalse($handler->isHandling($record));
        $this->assertFalse($handler->handle($record));
    }

    public function testLegacyIsHandlingUsesMinimalLevelRecord(): void
    {
        if (Logger::API >= 3) {
            $this->markTestSkipped('Test only works for Monolog < 3');
        }

        $handler = new LogToSentryIssueHandler(Logger::WARNING);

        $this->assertTrue($handler->isHandling(['level' => Logger::WARNING]));
        $this->assertFalse($handler->isHandling(['level' => Logger::INFO]));
    }

    public function testLogAndExceptionIssueHandlersReplaceLegacyHandlerUseCases(): void
    {
        $client = ClientBuilder::create()
            ->setTransport(StubTransport::getInstance())
            ->getClient();
        SentrySdk::init($client);

        $logger = new Logger('channel.foo', [
            new LogToSentryIssueHandler(Logger::WARNING, true, true),
            new ExceptionToSentryIssueHandler(Logger::WARNING),
        ]);

        $logger->warning('plain warning', [
            'foo' => 'bar',
        ]);

        $exception = new \RuntimeException('boom');
        $logger->error('exception error', [
            'exception' => $exception,
            'foo' => 'bar',
        ]);

        $this->assertCount(2, StubTransport::$events);

        $logEvent = StubTransport::$events[0];
        $this->assertSame('plain warning', $logEvent->getMessage());
        $this->assertEquals(Severity::warning(), $logEvent->getLevel());
        $this->assertSame('monolog.channel.foo', $logEvent->getLogger());
        $this->assertSame([], $logEvent->getExceptions());
        $this->assertSame([
            'monolog.channel' => 'channel.foo',
            'monolog.level' => Logger::getLevelName(Logger::WARNING),
            'monolog.context' => [
                'foo' => 'bar',
            ],
        ], $logEvent->getExtra());

        $exceptionEvent = StubTransport::$events[1];
        $this->assertNull($exceptionEvent->getMessage());
        $this->assertCount(1, $exceptionEvent->getExceptions());
        $this->assertSame(\RuntimeException::class, $exceptionEvent->getExceptions()[0]->getType());
        $this->assertSame('boom', $exceptionEvent->getExceptions()[0]->getValue());
        $this->assertSame([
            'monolog.channel' => 'channel.foo',
            'monolog.level' => Logger::getLevelName(Logger::ERROR),
            'monolog.message' => 'exception error',
            'monolog.context' => [
                'foo' => 'bar',
            ],
        ], $exceptionEvent->getExtra());
    }

    /**
     * @return iterable<array{bool, LogRecord|array<string, mixed>, Severity, array<string, mixed>}>
     */
    public static function capturedRecordsDataProvider(): iterable
    {
        foreach ([
            Logger::DEBUG => Severity::debug(),
            Logger::INFO => Severity::info(),
            Logger::NOTICE => Severity::info(),
            Logger::WARNING => Severity::warning(),
            Logger::ERROR => Severity::error(),
            Logger::CRITICAL => Severity::fatal(),
            Logger::ALERT => Severity::fatal(),
            Logger::EMERGENCY => Severity::fatal(),
        ] as $level => $severity) {
            yield Logger::getLevelName($level) => [
                false,
                RecordFactory::create('foo bar', $level, 'channel.foo', [], []),
                $severity,
                [
                    'monolog.channel' => 'channel.foo',
                    'monolog.level' => Logger::getLevelName($level),
                ],
            ];
        }

        yield 'with context and extra' => [
            true,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'foo' => 'bar',
                ],
                [
                    'bar' => 'baz',
                ]
            ),
            Severity::warning(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
                'monolog.context' => [
                    'foo' => 'bar',
                ],
                'monolog.extra' => [
                    'bar' => 'baz',
                ],
            ],
        ];

        yield 'without context and extra by default' => [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'foo' => 'bar',
                ],
                [
                    'bar' => 'baz',
                ]
            ),
            Severity::warning(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];
    }
}
