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
 * This processor removes all the cookies from the request to ensure no sensitive
 * informations are sent to the server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RemoveCookiesProcessor implements ProcessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(Event $event)
    {
        $request = $event->getRequest();

        if (isset($request['cookies'])) {
            $request['cookies'] = self::STRING_MASK;
        }

        if (isset($request['headers'], $request['headers']['cookie'])) {
            $request['headers']['cookie'] = self::STRING_MASK;
        }

        return $event->withRequest($request);
    }
}
