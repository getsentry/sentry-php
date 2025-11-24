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
