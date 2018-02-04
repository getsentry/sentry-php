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
 * This transport fakes the sending of events by just ignoring them.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class NullTransport implements TransportInterface
{
    /**
     * {@inheritdoc}
     */
    public function send(Event $event)
    {
        return true;
    }
}
