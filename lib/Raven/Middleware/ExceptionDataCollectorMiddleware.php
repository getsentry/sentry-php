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
 * This middleware collects information about the thrown exceptions.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ExceptionDataCollectorMiddleware
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
        if (!isset($payload['level'])) {
            $payload['level'] = Client::LEVEL_ERROR;

            if ($exception instanceof \ErrorException) {
                $payload['level'] = $this->client->translateSeverity($exception->getSeverity());
            }
        }

        $event = $event->withLevel($payload['level']);

        if (null !== $exception) {
            $exceptions = [];
            $currentException = $exception;

            do {
                if ($this->client->getConfig()->isExcludedException($currentException)) {
                    continue;
                }

                $exceptions[] = [
                    'type' => get_class($currentException),
                    'value' => $currentException->getMessage(),
                    'stacktrace' => Stacktrace::createFromBacktrace($this->client, $currentException->getTrace(), $currentException->getFile(), $currentException->getLine()),
                ];
            } while ($currentException = $currentException->getPrevious());

            $exceptions = array_reverse($exceptions);

            $event = $event->withException($exceptions);
        }

        return $next($event, $request, $exception, $payload);
    }
}
