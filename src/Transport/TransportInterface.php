<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Transport;

use Sentry\Event;

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
     * @return null|string Returns the ID of the event or `null` if it failed to be sent
     */
    public function send(Event $event): ?string;
}
