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
final class RequestIntegration implements Integration
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

            /** @var ServerRequestInterface $request */
            $request = isset($_SERVER['REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null;

            if (null == $request) {
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

//            TODO
//            /** @var UserContext $userContext */
//            $userContext = $event->getUserContext();
//
//            if (!isset($userContext['ip_address']) && null !== $request && $request->hasHeader('REMOTE_ADDR')) {
//                $userContext['ip_address'] = $request->getHeaderLine('REMOTE_ADDR');
//            }

            return $event;
        });
    }
}
