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
            $event->setRequest($this->serializer->serialize($request, 5));
        }

        $event->getUserContext()->replaceData($this->serializer->serialize($event->getUserContext()->toArray()));
        $event->getRuntimeContext()->replaceData($this->serializer->serialize($event->getRuntimeContext()->toArray()));
        $event->getServerOsContext()->replaceData($this->serializer->serialize($event->getServerOsContext()->toArray()));
        $event->getExtraContext()->replaceData($this->serializer->serialize($event->getExtraContext()->toArray()));
        $event->getTagsContext()->replaceData($this->serializer->serialize($event->getTagsContext()->toArray()));

        return $next($event, $request, $exception, $payload);
    }
}
