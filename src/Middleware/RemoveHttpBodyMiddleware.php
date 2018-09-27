<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Raven\Event;

/**
 * This middleware removes all the data of the HTTP body to ensure no sensitive
 * information are sent to the server in case the request method is POST, PUT,
 * PATCH or DELETE.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RemoveHttpBodyMiddleware implements ProcessorMiddlewareInterface
{
    /**
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param callable                    $next      The next middleware to call
     * @param ServerRequestInterface|null $request   The request, if available
     * @param \Exception|\Throwable|null  $exception The thrown exception, if available
     * @param array                       $payload   Additional data
     *
     * @return Event
     */
    public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = [])
    {
        $request = $event->getRequest();

        if (isset($request['method']) && \in_array(strtoupper($request['method']), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $request['data'] = self::STRING_MASK;
        }

        $event->setRequest($request);

        return $next($event, $request, $exception, $payload);
    }
}
