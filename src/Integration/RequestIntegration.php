<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Zend\Diactoros\ServerRequestFactory;

/**
 * This integration collects information from the request and attaches them to
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

            self::applyToEvent($event);

            return $event;
        });
    }

    /**
     * @param Event                       $event   The event that will be enriched with a request
     * @param null|ServerRequestInterface $request The Request that will be processed and added to the event
     */
    public static function applyToEvent(Event $event, ?ServerRequestInterface $request = null): void
    {
        if (null === $request) {
            /** @var ?ServerRequestInterface $request */
            $request = isset($_SERVER['REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null;
        }

        if (null === $request) {
            return;
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

        $userContext = $event->getUserContext();

        if (null === $userContext->getIpAddress() && $request->hasHeader('REMOTE_ADDR')) {
            $userContext->setIpAddress($request->getHeaderLine('REMOTE_ADDR'));
        }
    }
}
