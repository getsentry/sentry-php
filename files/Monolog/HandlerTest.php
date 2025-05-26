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
    public function testHandle(bool $fillExtraContext, $record, Event $expectedEvent, EventHint $expectedHint, array $expectedExtra): void
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

        $handler = new Handler(new Hub($client, new Scope()), Logger::DEBUG, true, $fillExtraContext);
        $handler->handle($record);
    }

    public static function handleDataProvider(): iterable
    {
        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::debug());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::DEBUG,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::DEBUG),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::info());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::INFO,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::INFO),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::info());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::NOTICE,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::NOTICE),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::error());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::ERROR,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::ERROR),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::fatal());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::CRITICAL,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::CRITICAL),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::fatal());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::ALERT,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::ALERT),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::fatal());

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::EMERGENCY,
                'channel.foo',
                [],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::EMERGENCY),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        $exampleException = new \Exception('exception message');

        yield [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'exception' => $exampleException,
                ],
                []
            ),
            $event,
            EventHint::fromArray([
                'exception' => $exampleException,
            ]),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield 'Monolog\'s context is filled and the handler should fill the "extra" context' => [
            true,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
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
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'exception' => new \Exception('exception message'),
                ],
                []
            ),
            $event,
            EventHint::fromArray([
                'exception' => $exampleException,
            ]),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield 'Monolog\'s context is filled but handler should not fill the "extra" context' => [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
                []
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];
        yield 'Monolog\'s extra is filled and the handler should fill the "extra" context' => [
            true,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [],
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ]
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
                'monolog.extra' => [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
            ],
        ];

        $event = Event::createEvent();
        $event->setMessage('foo bar');
        $event->setLogger('monolog.channel.foo');
        $event->setLevel(Severity::warning());

        yield 'Monolog\'s extra is filled but handler should not fill the "extra" context' => [
            false,
            RecordFactory::create(
                'foo bar',
                Logger::WARNING,
                'channel.foo',
                [],
                [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ]
            ),
            $event,
            new EventHint(),
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
        ];
    }
}
