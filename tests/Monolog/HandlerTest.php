<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Monolog\Handler;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class HandlerTest extends TestCase
{
    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(bool $fillExtraContext, array $record, Event $expectedEvent, EventHint $expectedHint, array $expectedExtra): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with(
                $this->callback(function (Event $event) use ($expectedEvent): bool {
                    $this->assertEquals($expectedEvent->getLevel(), $event->getLevel());
                    $this->assertSame($expectedEvent->getMessage(), $event->getMessage());
                    $this->assertSame($expectedEvent->getLogger(), $event->getLogger());

                    return true;
                }),
                $expectedHint,
                $this->callback(function (Scope $scopeArg) use ($expectedExtra): bool {
                    $event = $scopeArg->applyToEvent(Event::createEvent());

                    $this->assertNotNull($event);
                    $this->assertSame($expectedExtra, $event->getExtra());

                    return true;
                })
            );

        $handler = new Handler(new Hub($client, new Scope()), Level::Debug, true, $fillExtraContext);
        $handler->handle(
            new LogRecord(
                $record['time'],
                $record['channel'],
                $record['level'],
                $record['message'],
                $record['context'],
                $record['extra'],
                $record['extra']
            )
        );
    }

    public function handleDataProvider(): iterable
    {
        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::debug());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Debug,
                'level_name' => Level::Debug->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Level::Debug),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::info());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Info,
                'level_name' => Level::Info->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Info->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::info());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Notice,
                'level_name' => Level::Notice->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Notice->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Warning,
                'level_name' => Level::Warning->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Warning->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::error());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Error,
                'level_name' => Level::Error->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Error->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::fatal());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Critical,
                'level_name' => Level::Critical->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Critical->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::fatal());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Alert,
                'level_name' => Level::Alert->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Alert->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::fatal());

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Emergency,
                'level_name' => Level::Emergency->getName(),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Emergency->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        $exampleException = new \Exception('exception message');

        yield [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Warning,
                'level_name' => Level::Warning->getName(),
                'context' => [
                    'exception' => $exampleException,
                ],
                'channel' => 'channel.foo',
                'extra' => [],
            ],
            $event,
            EventHint::fromArray([
                'exception' => $exampleException,
            ]),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Warning->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield 'Monolog\'s context is filled and the handler should fill the "extra" context' => [
            true,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Warning,
                'level_name' => Level::Warning->getName(),
                'context' => [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
                'channel' => 'channel.foo',
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Warning->getName(),
                'monolog.context' => [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield 'Monolog\'s context is filled with "exception" field and the handler should fill the "extra" context' => [
            true,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Warning,
                'level_name' => Level::Warning->getName(),
                'context' => [
                    'exception' => new \Exception('exception message'),
                ],
                'channel' => 'channel.foo',
                'extra' => [],
            ],
            $event,
            EventHint::fromArray([
                'exception' => $exampleException,
            ]),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Warning->getName(),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield 'Monolog\'s context is filled but handler should not fill the "extra" context' => [
            false,
            [
                'time' => new DateTimeImmutable('2022-05-28 18:00:00'),
                'message' => 'foo bar',
                'level' => Level::Warning,
                'level_name' => Level::Warning->getName(),
                'context' => [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
                'channel' => 'channel.foo',
                'extra' => [],
            ],
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Level::Warning->getName(),
            ],
        ];
    }
}
