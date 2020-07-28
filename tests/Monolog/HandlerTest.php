<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Monolog\Handler;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class HandlerTest extends TestCase
{
    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(array $record, array $expectedPayload, array $expectedExtra, array $expectedTags): void
    {
        $scope = new Scope();

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('captureEvent')
            ->with($expectedPayload, $this->callback(function (Scope $scopeArg) use ($expectedExtra, $expectedTags): bool {
                $event = $scopeArg->applyToEvent(new Event(), []);

                $this->assertNotNull($event);
                $this->assertSame($expectedExtra, $event->getExtraContext()->toArray());
                $this->assertSame($expectedTags, $event->getTagsContext()->toArray());

                return true;
            }));

        $handler = new Handler(new Hub($client, $scope));
        $handler->handle($record);
    }

    public function handleDataProvider(): \Generator
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
            [
                'level' => Severity::debug(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::DEBUG),
            ],
            [],
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
            [
                'level' => Severity::info(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::INFO),
            ],
            [],
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
            [
                'level' => Severity::info(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::NOTICE),
            ],
            [],
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
            [
                'level' => Severity::warning(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
            ],
            [],
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
            [
                'level' => Severity::error(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::ERROR),
            ],
            [],
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
            [
                'level' => Severity::fatal(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::CRITICAL),
            ],
            [],
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
            [
                'level' => Severity::fatal(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::ALERT),
            ],
            [],
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
            [
                'level' => Severity::fatal(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::EMERGENCY),
            ],
            [],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::WARNING,
                'level_name' => Logger::getLevelName(Logger::WARNING),
                'context' => [
                    'extra' => [
                        'foo.extra' => 'foo extra value',
                        'bar.extra' => 'bar extra value',
                    ],
                    'tags' => [
                        'foo.tag' => 'foo tag value',
                        'bar.tag' => 'bar tag value',
                    ],
                ],
                'channel' => 'channel.foo',
                'datetime' => new \DateTimeImmutable(),
                'extra' => [],
            ],
            [
                'level' => Severity::warning(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
                'foo.extra' => 'foo extra value',
                'bar.extra' => 'bar extra value',
            ],
            [
                'foo.tag' => 'foo tag value',
                'bar.tag' => 'bar tag value',
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::WARNING,
                'level_name' => Logger::getLevelName(Logger::WARNING),
                'context' => [
                    'exception' => new \Exception('exception message'),
                    'extra' => [
                        'foo.extra' => 'foo extra value',
                        'bar.extra' => 'bar extra value',
                    ],
                    'tags' => [
                        'foo.tag' => 'foo tag value',
                        'bar.tag' => 'bar tag value',
                    ],
                ],
                'channel' => 'channel.foo',
                'datetime' => new \DateTimeImmutable(),
                'extra' => [],
            ],
            [
                'level' => Severity::warning(),
                'message' => 'foo bar',
                'exception' => new \Exception('exception message'),
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::WARNING),
                'foo.extra' => 'foo extra value',
                'bar.extra' => 'bar extra value',
            ],
            [
                'foo.tag' => 'foo tag value',
                'bar.tag' => 'bar tag value',
            ],
        ];

        yield [
            [
                'message' => 'foo bar',
                'level' => Logger::INFO,
                'level_name' => Logger::getLevelName(Logger::INFO),
                'channel' => 'channel.foo',
                'context' => [
                    'extra' => [
                        1 => 'numeric key',
                    ],
                ],
                'extra' => [],
            ],
            [
                'level' => Severity::info(),
                'message' => 'foo bar',
                'logger' => 'monolog.channel.foo',
            ],
            [
                'monolog.channel' => 'channel.foo',
                'monolog.level' => Logger::getLevelName(Logger::INFO),
                '0' => 'numeric key',
            ],
            [],
        ];
    }
}
