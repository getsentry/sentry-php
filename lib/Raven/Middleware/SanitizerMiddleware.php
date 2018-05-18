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
use Raven\Serializer;

/**
 * This middleware must run at the end of the chain to sanitize the data being
 * sent to the Sentry server.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class SanitizerMiddleware
{
    /**
     * @var Serializer The serializer instance
     */
    private $serializer;

    /**
     * Constructor.
     *
     * @param Serializer $serializer The serializer instance
     */
    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

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
        if (!empty($request = $event->getRequest())) {
            $event = $event->withRequest($this->serializer->serialize($request, 5));
        }

        if (!empty($userContext = $event->getUserContext())) {
            $event = $event->withUserContext($this->serializer->serialize($userContext));
        }

        if (!empty($runtimeContext = $event->getRuntimeContext())) {
            $event = $event->withRuntimeContext($this->serializer->serialize($runtimeContext));
        }

        if (!empty($serverOsContext = $event->getServerOsContext())) {
            $event = $event->withServerOsContext($this->serializer->serialize($serverOsContext));
        }

        if (!empty($extraContext = $event->getExtraContext())) {
            $event = $event->withExtraContext($this->serializer->serialize($extraContext));
        }

        if (!empty($tagsContext = $event->getTagsContext())) {
            $event = $event->withTagsContext($this->serializer->serialize($tagsContext));
        }

        return $next($event, $request, $exception, $payload);
    }
}
