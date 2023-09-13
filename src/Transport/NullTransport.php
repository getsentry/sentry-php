<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Sentry\Event;

final class NullTransport implements TransportInterface
{
    /**
     * {@inheritdoc}
     */
    public function send(Event $event): Result
    {
        return new Result(ResultStatus::skipped(), $event);
    }

    /**
     * {@inheritdoc}
     */
    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
