<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Integration;

use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequestFactory;
use Sentry\Event;

/**
 * This middleware collects information from the request and attaches them to
 * the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RequestIntegration
{
    /**
     * Collects the needed data and sets it in the given event object.
     *
     * @param Event                       $event     The event being processed
     * @param \Exception|\Throwable|null  $exception The thrown exception, if available
     *
     * @return Event
     */
    public function __invoke(Event $event, $exception = null)
    {
        /** @var ServerRequestInterface $request*/
        $request = isset($_SERVER[ 'REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null;

        if (null === $request) {
            return $event;
        }

        $requestData = [
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
            'headers' => $request->getHeaders(),
            'cookies' => $request->getCookieParams(),
        ];

        if ('' !== $request->getUri()->getQuery()) {
            $requestData['query_string'] = $request->getUri()->getQuery();
        }

        if ($request->hasHeader('REMOTE_ADDR')) {
            $requestData['env']['REMOTE_ADDR'] = $request->getHeaderLine('REMOTE_ADDR');
        }

        $event->setRequest($requestData);

        /** @var array|Context $userContext */
        $userContext = $event->getUserContext();

        if (!isset($userContext['ip_address']) && null !== $request && $request->hasHeader('REMOTE_ADDR')) {
            $userContext['ip_address'] = $request->getHeaderLine('REMOTE_ADDR');
        }

        return $event;
    }
}
