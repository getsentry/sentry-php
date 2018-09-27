<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Spool;

use Sentry\Event;
use Sentry\Transport\TransportInterface;

/**
 * This interface must be implemented by all classes willing to provide the
 * storage of a spool.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface SpoolInterface
{
    /**
     * Queues an event.
     *
     * @param Event $event The event to store
     *
     * @return bool Whether the operation has succeeded
     */
    public function queueEvent(Event $event);

    /**
     * Sends the events that are in the queue using the given transport.
     *
     * @param TransportInterface $transport The transport instance
     */
    public function flushQueue(TransportInterface $transport);
}
