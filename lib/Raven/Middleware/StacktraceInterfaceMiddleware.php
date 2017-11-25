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
use Raven\Stacktrace;

/**
 * This middleware collects information about the error or exception that
 * generated the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class StacktraceInterfaceMiddleware
{
    /**
     * @var Client The Raven client
     */
    private $client;

    /**
     * Constructor.
     *
     * @param Client $client The Raven client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
        $config = $this->client->getConfig();

        if (null !== $exception && $config->getAutoLogStacks() && !$config->isExcludedException($exception)) {
            $event = $event->withStacktrace(Stacktrace::createFromBacktrace($this->client, $exception->getTrace(), $exception->getFile(), $exception->getLine()));
        }

        return $next($event, $request, $exception, $payload);
    }
}
