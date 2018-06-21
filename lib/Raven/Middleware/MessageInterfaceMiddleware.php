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
use Raven\Client;
use Raven\Event;

/**
 * This middleware collects the needed data to store a message with optional
 * params into the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class MessageInterfaceMiddleware
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
        $message = isset($payload['message']) ? $payload['message'] : null;
        $messageParams = isset($payload['message_params']) ? $payload['message_params'] : [];

        if (null !== $message) {
            $event = $event->withMessage(substr($message, 0, Client::MESSAGE_MAX_LENGTH_LIMIT), $messageParams);
        }

        return $next($event, $request, $exception, $payload);
    }
}
