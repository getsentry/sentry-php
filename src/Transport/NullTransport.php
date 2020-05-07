<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Sentry\Event;

/**
 * This transport fakes the sending of events by just ignoring them.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 *
 * @final since 2.3
 */
class NullTransport implements TransportInterface
{
    /**
     * {@inheritdoc}
     */
    public function send(Event $event): ?string
    {
        return (string) $event->getId(false);
    }
}
