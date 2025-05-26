<?php

declare(strict_types=1);

namespace Sentry\Tests\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;

/**
 * @internal
 */
final class RecordFactory
{
    /**
     * @param Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY $level   The Monolog log level
     * @param array<string, mixed>                                                                                                     $context
     * @param array<string, mixed>                                                                                                     $extra
     *
     * @return array<string, mixed>|LogRecord
     */
    public static function create(string $message, int $level, string $channel, array $context, array $extra)
    {
        if (Logger::API >= 3) {
            return new LogRecord(
                new \DateTimeImmutable(),
                $channel,
                Logger::toMonologLevel($level),
                $message,
                $context,
                $extra
            );
        }

        return [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => $channel,
            'extra' => $extra,
            'datetime' => new \DateTimeImmutable(),
        ];
    }
}
