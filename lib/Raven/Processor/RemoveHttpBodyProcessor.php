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
 * This processor removes all the data of the HTTP body to ensure no sensitive
 * informations are sent to the server in case the request method is POST, PUT,
 * PATCH or DELETE.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RemoveHttpBodyProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(Event $event)
    {
        $request = $event->getRequest();

        if (isset($request['method']) && in_array(strtoupper($request['method']), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $request['data'] = self::STRING_MASK;
        }

        return $event->withRequest($request);
    }
}
