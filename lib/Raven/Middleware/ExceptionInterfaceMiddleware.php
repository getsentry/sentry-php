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
final class ExceptionInterfaceMiddleware
{
    /**
     * @var Client The Raven client
     */
    private $client;

    /**
     * @var \Raven\Configuration The client configuration
     */
    private $configuration;

    /**
     * Constructor.
     *
     * @param Client $client The Raven client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->configuration = $client->getConfig();
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
        // Do not override the level if it was set explicitly by the user
        if (!isset($payload['level']) && $exception instanceof \ErrorException) {
            $event = $event->withLevel($this->client->translateSeverity($exception->getSeverity()));
        }

        if (null !== $exception) {
            $exceptions = [];
            $currentException = $exception;

            do {
                if ($this->configuration->isExcludedException($currentException)) {
                    continue;
                }

                $data = [
                    'type' => get_class($currentException),
                    'value' => $this->configuration->getSerializer()->serialize($currentException->getMessage()),
                ];

                if ($this->configuration->getAutoLogStacks()) {
                    $data['stacktrace'] = Stacktrace::createFromBacktrace(
                        $this->configuration,
                        $currentException->getTrace(),
                        $currentException->getFile(),
                        $currentException->getLine()
                    );
                }

                $exceptions[] = $data;
            } while ($currentException = $currentException->getPrevious());

            $exceptions = [
                'values' => array_reverse($exceptions),
            ];

            $event = $event->withException($exceptions);
        }

        return $next($event, $request, $exception, $payload);
    }
}
