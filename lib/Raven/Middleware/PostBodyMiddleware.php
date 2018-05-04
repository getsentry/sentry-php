<?php

namespace Raven\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Raven\Event;

final class PostBodyMiddleware
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
        if ($request && $request->getBody()->getSize()) {
            $requestData = $event->getRequest();
            $requestData['data'] = $request->getBody();
            $event = $event->withRequest($requestData);
        }

        return $next($event, $request, $exception, $payload);
    }
}
