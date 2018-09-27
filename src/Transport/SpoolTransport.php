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
use Sentry\Spool\SpoolInterface;

/**
 * This transport stores the events in a queue to send them later.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SpoolTransport implements TransportInterface
{
    /**
     * @var SpoolInterface The spool instance
     */
    private $spool;

    /**
     * Constructor.
     *
     * @param SpoolInterface $spool The spool instance
     */
    public function __construct(SpoolInterface $spool)
    {
        $this->spool = $spool;
    }

    /**
     * Gets the spool.
     *
     * @return SpoolInterface
     */
    public function getSpool()
    {
        return $this->spool;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Event $event)
    {
        return $this->spool->queueEvent($event);
    }
}
