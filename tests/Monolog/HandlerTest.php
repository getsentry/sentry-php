<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
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
    public function testHandle(array $record, Event $expectedEvent, EventHint $expectedHint, array $expectedExtra): void
    {
        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($this->callback(function (Event $event) use ($expectedEvent): bool {
                $this->assertEquals($expectedEvent->getLevel(), $event->getLevel());
                $this->assertEquals($expectedEvent->getMessage(), $event->getMessage());
                $this->assertEquals($expectedEvent->getLogger(), $event->getLogger());

                return true;
            }), $this->callback(function (EventHint $hint) use ($expectedHint): bool {
                $this->assertEquals($hint, $expectedHint);

                return true;
            }), $this->callback(function (Scope $scopeArg) use ($expectedExtra): bool {
                $event = $scopeArg->applyToEvent(Event::createEvent());

                $this->assertNotNull($event);
                $this->assertSame($expectedExtra, $event->getExtra());

                return true;
            }));

        $handler = new Handler(new Hub($client, new Scope()));
        $handler->handle($record);
    }

    public function handleDataProvider(): iterable
    {
        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::DEBUG,
                'level_name' => Logger::getLevelName(Logger::DEBUG),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::debug());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::DEBUG),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::INFO,
                'level_name' => Logger::getLevelName(Logger::INFO),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::info());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::INFO),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::NOTICE,
                'level_name' => Logger::getLevelName(Logger::NOTICE),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::info());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::NOTICE),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::WARNING,
                'level_name' => Logger::getLevelName(Logger::WARNING),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::warning());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::ERROR,
                'level_name' => Logger::getLevelName(Logger::ERROR),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::error());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::ERROR),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::CRITICAL,
                'level_name' => Logger::getLevelName(Logger::CRITICAL),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::fatal());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::CRITICAL),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::ALERT,
                'level_name' => Logger::getLevelName(Logger::ALERT),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::fatal());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::ALERT),
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::EMERGENCY,
                'level_name' => Logger::getLevelName(Logger::EMERGENCY),
                'channel' => 'channel.foo',
                'context' => [],
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::fatal());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::EMERGENCY),
            ],
        ];

        $exampleException = new \Exception('exception message');

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::WARNING,
                'level_name' => Logger::getLevelName(Logger::WARNING),
                'context' => [
                    'exception' => $exampleException,
                ],
                'channel' => 'channel.foo',
                'extra' => [],
            ],
            (static function (): Event {
                $event = Event::createEvent();

                $event->setLevel(Severity::warning());
                $event->setMessage('foo bar');
                $event->setLogger('monolog.channel.foo');

                return $event;
            })(),
            EventHint::fromArray([
                'exception' => $exampleException,
            ]),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];
    }
}
