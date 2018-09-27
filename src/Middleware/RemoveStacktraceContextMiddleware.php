<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;

/**
 * This middleware removes the `pre_context`, `context_line` and `post_context`
 * information from all exception frames captured by an event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RemoveStacktraceContextMiddleware implements ProcessorMiddlewareInterface
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
        $stacktrace = $event->getStacktrace();

        if (null !== $stacktrace) {
            foreach ($stacktrace->getFrames() as $frame) {
                $frame->setPreContext(null);
                $frame->setContextLine(null);
                $frame->setPostContext(null);
            }

            $event->setStacktrace($stacktrace);
        }

        return $next($event, $request, $exception, $payload);
    }
}
