<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Processor;

use Raven\Event;

/**
 * This interface defines a contract that must be implemented by all classes
 * willing to process the data logged after an error has occurred before it's
 * sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
interface ProcessorInterface
{
    /**
     * This constant defines the mask string used to strip sensitive informations.
     */
    const STRING_MASK = '********';

    /**
     * Process and sanitize data, modifying the existing value if necessary.
     *
     * @param Event $event The event object
     *
     * @return Event
     */
    public function process(Event $event);
}
