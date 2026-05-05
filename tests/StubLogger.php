<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Psr\Log\AbstractLogger;

class StubLogger extends AbstractLogger
{
    public static $logs = [];

    /**
     * @var self
     */
    private static $instance;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new StubLogger();
        }

        return self::$instance;
    }

    /**
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        self::$logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
