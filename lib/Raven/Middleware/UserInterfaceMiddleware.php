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
 * This middleware collects information about the current authenticated user.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class UserInterfaceMiddleware
{
    /**
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param callable                    $next      The next middleware to call
     * @param ServerRequestInterface|null $request   The request, if available
     * @param \Exception|null             $exception The thrown exception, if available
     * @param array                       $payload   Additional data
     *
     * @return Event
     */
    public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = [])
    {
        $userContext = $event->getUserContext();

        if (!isset($userContext['ip_address']) && null !== $request && $request->hasHeader('REMOTE_ADDR')) {
            $userContext['ip_address'] = $request->getHeaderLine('REMOTE_ADDR');
        }

        $event = $event->withUserContext($userContext);

        return $next($event, $request, $exception, $payload);
    }
}
