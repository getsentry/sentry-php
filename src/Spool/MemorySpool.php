<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Spool;

use Raven\Event;
use Raven\Transport\TransportInterface;

/**
 * This spool stores the events in memory.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class MemorySpool implements SpoolInterface
{
    /**
     * @var Event[] List of enqueued events
     */
    private $events = [];

    /**
     * {@inheritdoc}
     */
    public function queueEvent(Event $event)
    {
        $this->events[] = $event;
    }

    /**
     * {@inheritdoc}
     */
    public function flushQueue(TransportInterface $transport)
    {
        if (empty($this->events)) {
            return;
        }

        while ($event = array_pop($this->events)) {
            $transport->send($event);
        }
    }
}
