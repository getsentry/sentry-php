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
use Sentry\Breadcrumbs\Recorder;
use Sentry\Event;

/**
 * This middleware collects all the recorded breadcrumbs up to this moment and
 * adds them to the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class BreadcrumbInterfaceMiddleware
{
    /**
     * @var Recorder The breadcrumbs recorder
     */
    private $recorder;

    /**
     * Constructor.
     *
     * @param Recorder $recorder The breadcrumbs recorder
     */
    public function __construct(Recorder $recorder)
    {
        $this->recorder = $recorder;
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
        foreach ($this->recorder as $breadcrumb) {
            $event->setBreadcrumb($breadcrumb);
        }

        return $next($event, $request, $exception, $payload);
    }
}
