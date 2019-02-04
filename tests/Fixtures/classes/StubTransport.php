<?php

namespace Sentry\Tests\Fixtures\classes;

use Sentry\Event;
use Sentry\Transport\TransportInterface;

class StubTransport implements TransportInterface
{
    /** @var Event[] */
    private $events = [];
    
    /** @var Event|null */
    private $lastSent;

    public function send(Event $event): ?string
    {
        $this->events[] = $event;
        $this->lastSent = $event;

        return $event->getId();
    }

    /**
     * @return Event[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getLastSent(): ?Event
    {
        return $this->lastSent;
    }
}
