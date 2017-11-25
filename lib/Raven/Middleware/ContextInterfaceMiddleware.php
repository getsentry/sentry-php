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
use Raven\Context;
use Raven\Event;

/**
 * This middleware collects additional context data. Typically this is data
 * related to the current user or the current HTTP request.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ContextInterfaceMiddleware
{
    /**
     * @var Context The context storage
     */
    private $context;

    /**
     * Constructor.
     *
     * @param Context $context The context storage
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

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
        $tagsContext = isset($payload['tags_context']) ? $payload['tags_context'] : [];
        $extraContext = isset($payload['extra_context']) ? $payload['extra_context'] : [];
        $serverOsContext = isset($payload['server_os_context']) ? $payload['server_os_context'] : [];
        $runtimeContext = isset($payload['runtime_context']) ? $payload['runtime_context'] : [];
        $userContext = isset($payload['user_context']) ? $payload['user_context'] : [];

        $event = $event->withTagsContext(array_merge($this->context->getTags(), $tagsContext))
            ->withExtraContext(array_merge($this->context->getExtraData(), $extraContext))
            ->withUserContext(array_merge($this->context->getUserData(), $userContext))
            ->withServerOsContext($serverOsContext)
            ->withRuntimeContext($runtimeContext);

        return $next($event, $request, $exception, $payload);
    }
}
