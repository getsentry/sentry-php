<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\ClientInterface;
use Sentry\Monolog\Handler;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;

final class HandlerTest extends TestCase
{
    /**
     * @var MockObject|ClientInterface
     */
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(array $record, array $expectedPayload, array $expectedExtra, array $expectedTags): void
    {
        $this->client->expects($this->once())
            ->method('captureEvent')
            ->with($expectedPayload, $this->callback(static function (Scope $scope) use ($expectedExtra, $expectedTags): bool {
                if ($expectedExtra !== $scope->getExtra()) {
                    return false;
                }

                if ($expectedTags !== $scope->getTags()) {
                    return false;
                }

                return true;
            }));

        $handler = new Handler(new Hub($this->client));
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
    }
}
