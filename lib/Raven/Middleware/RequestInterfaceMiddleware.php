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
 * This middleware collects information from the request and attaches them to
 * the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class RequestInterfaceMiddleware
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
    public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, \Exception $exception = null, array $payload = [])
    {
        if (null === $request) {
            return $next($event, $request, $exception, $payload);
        }

        $requestData = [
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
            'headers' => $request->getHeaders(),
            'cookies' => $request->getCookieParams(),
        ];

        if ('' !== $request->getUri()->getQuery()) {
            $requestData['query_string'] = $request->getUri()->getQuery();
        }

        if ($request->hasHeader('REMOTE_ADDR')) {
            $requestData['env']['REMOTE_ADDR'] = $request->getHeaderLine('REMOTE_ADDR');
        }

        $event = $event->withRequest($requestData);

        return $next($event, $request, $exception, $payload);
    }
}
