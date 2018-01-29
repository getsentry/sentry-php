<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Transport;

use Raven\Event;

/**
 * This interface must be implemented by all classes willing to provide a way
 * of sending events to a Sentry server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface TransportInterface
{
    /**
     * Sends the given event.
     *
     * @param Event $event The event
     *
     * @return bool Whether the event was sent successfully or not
     */
    public function send(Event $event);
}
