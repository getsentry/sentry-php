<?php

declare(strict_types=1);

namespace Sentry\Tests\Logger;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Sentry\Logger\DebugLogger;

final class DebugLoggerTest extends TestCase
{
    /**
     * @param mixed $level
     *
     * @dataProvider logFormatsLevelDataProvider
     */
    public function testLogFormatsLevel($level, string $expectedMessage): void
    {
        $logger = new class extends DebugLogger {
            /**
             * @var string[]
             */
            public $messages = [];

            public function write(string $message): void
            {
                $this->messages[] = $message;
            }
        };

        $logger->log($level, 'foo');

        $this->assertSame([$expectedMessage], $logger->messages);
    }

    public static function logFormatsLevelDataProvider(): \Generator
    {
        yield 'string level' => [LogLevel::INFO, "sentry/sentry: [info] foo []\n"];

        yield 'int level' => [200, "sentry/sentry: [200] foo []\n"];

        yield 'non-stringable level' => [[], "sentry/sentry: [array] foo []\n"];
    }
}
