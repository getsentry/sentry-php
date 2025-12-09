<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Transport used for testing that keeps all events in memory.
 */
class StubTransport implements TransportInterface
{
    /**
     * @var Event[]
     */
    public static $events = [];

    /**
     * @var self
     */
    private static $instance;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function send(Event $event): Result
    {
        self::$events[] = $event;

        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
