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
use Raven\Context\Context;
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
     * @var Context The context
     */
    private $context;

    /**
     * @var string The alias name of the context
     */
    private $contextName;

    /**
     * Constructor.
     *
     * @param Context $context     The context
     * @param string  $contextName The alias name of the context
     */
    public function __construct(Context $context, $contextName)
    {
        $this->context = $context;
        $this->contextName = $contextName;
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
        $context = isset($payload[$this->contextName . '_context']) ? $payload[$this->contextName . '_context'] : [];
        $context = array_merge($this->context->toArray(), $context);

        switch ($this->contextName) {
            case Context::CONTEXT_USER:
                $event->setUserContext($context, false);
                break;
            case Context::CONTEXT_RUNTIME:
                $event->setRuntimeContext($context, false);
                break;
            case Context::CONTEXT_TAGS:
                $event->setTagsContext($context, false);
                break;
            case Context::CONTEXT_EXTRA:
                $event->setExtraContext($context, false);
                break;
            case Context::CONTEXT_SERVER_OS:
                $event->setServerOsContext($context, false);
                break;
            default:
                throw new \RuntimeException(sprintf('The "%s" context is not supported.', $this->contextName));
        }

        return $next($event, $request, $exception, $payload);
    }
}
