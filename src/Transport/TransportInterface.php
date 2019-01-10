<?php

declare(strict_types=1);

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
     * @return string|null Returns the ID of the event or `null` if it failed to be sent
     */
    public function send(Event $event): ?string;
}
