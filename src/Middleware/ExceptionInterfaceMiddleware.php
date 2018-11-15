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
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Severity;
use Sentry\Stacktrace;

/**
 * This middleware collects information about the thrown exceptions.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ExceptionInterfaceMiddleware
{
    /**
     * @var ClientInterface The Raven client
     */
    private $client;

    /**
     * Constructor.
     *
     * @param ClientInterface $client The Raven client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
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
        if (isset($payload['level'])) {
            $event->setLevel($payload['level']);
        } elseif ($exception instanceof \ErrorException) {
            $event->setLevel(new Severity($this->client->translateSeverity($exception->getSeverity())));
        }

        if (null !== $exception) {
            $exceptions = [];
            $currentException = $exception;

            do {
                if ($this->client->getOptions()->isExcludedException($currentException)) {
                    continue;
                }

                $data = [
                    'type' => \get_class($currentException),
                    'value' => $this->client->getSerializer()->serialize($currentException->getMessage()),
                ];

                if ($this->client->getOptions()->getAutoLogStacks()) {
                    $data['stacktrace'] = Stacktrace::createFromBacktrace($this->client, $currentException->getTrace(), $currentException->getFile(), $currentException->getLine());
                }

                $exceptions[] = $data;
            } while ($currentException = $currentException->getPrevious());

            $exceptions = [
                'values' => array_reverse($exceptions),
            ];

            $event->setException($exceptions);
        }

        return $next($event, $request, $exception, $payload);
    }
}
