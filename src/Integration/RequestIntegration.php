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
use Sentry\Context\UserContext;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Zend\Diactoros\ServerRequestFactory;

/**
 * This middleware collects information from the request and attaches them to
 * the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RequestIntegration implements IntegrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event) {
            $self = Hub::getCurrent()->getIntegration($this);
            if (!$self instanceof self) {
                return $event;
            }

            return self::applyToEvent($event);
        });
    }

    /**
     * @param Event                       $event
     * @param null|ServerRequestInterface $request
     *
     * @return Event
     */
    public static function applyToEvent(Event $event, ?ServerRequestInterface $request = null): Event
    {
        if (null == $request) {
            /** @var ServerRequestInterface $request */
            $request = isset($_SERVER['REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null;
        }

        if (null == $request) {
            return $event;
        }

        $requestData = [
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
            'headers' => $request->getHeaders(),
            'cookies' => $request->getCookieParams(),
        ];

        if ($request->getUri()->getQuery()) {
            $requestData['query_string'] = $request->getUri()->getQuery();
        }

        if ($request->hasHeader('REMOTE_ADDR')) {
            $requestData['env']['REMOTE_ADDR'] = $request->getHeaderLine('REMOTE_ADDR');
        }

        $event->setRequest($requestData);

        /** @var UserContext $userContext */
        $userContext = $event->getUserContext();

        if (null === $userContext->getIpAddress() && $request->hasHeader('REMOTE_ADDR')) {
            $userContext->setIpAddress($request->getHeaderLine('REMOTE_ADDR'));
        }

        return $event;
    }
}
