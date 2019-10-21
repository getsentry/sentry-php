<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Http\Promise\Promise as HttpPromiseInterface;
use Sentry\Event;

/**
 * Represents a pending request with associated event.
 */
class PendingRequest
{
    /**
     * @var HttpPromiseInterface
     */
    private $promise;

    /**
     * @var Event
     */
    private $event;

    public function __construct(HttpPromiseInterface $promise, Event $event)
    {
        $this->promise = $promise;
        $this->event = $event;
    }

    public function getPromise(): HttpPromiseInterface
    {
        return $this->promise;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }
}
