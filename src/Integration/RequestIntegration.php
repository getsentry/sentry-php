<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\Options;
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
     * @var Options The client options
     */
    private $options;

    /**
     * RequestIntegration constructor.
     *
     * @param Options $options The Client Options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            $self = Hub::getCurrent()->getIntegration(self::class);

            if (!$self instanceof self) {
                return $event;
            }

            self::applyToEvent($self, $event);

            return $event;
        });
    }

    /**
     * Applies the information gathered by the this integration to the event.
     *
     * @param self                        $self    The current instance of RequestIntegration
     * @param Event                       $event   The event that will be enriched with a request
     * @param null|ServerRequestInterface $request The Request that will be processed and added to the event
     */
    public static function applyToEvent(self $self, Event $event, ?ServerRequestInterface $request = null): void
    {
        if (null === $request) {
            $request = isset($_SERVER['REQUEST_METHOD']) && \PHP_SAPI !== 'cli' ? ServerRequestFactory::fromGlobals() : null;
        }

        if (null === $request) {
            return;
        }

        $requestData = [
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
        ];

        if ($request->getUri()->getQuery()) {
            $requestData['query_string'] = $request->getUri()->getQuery();
        }

        if ($self->options->shouldSendDefaultPii()) {
            if ($request->hasHeader('REMOTE_ADDR')) {
                $requestData['env']['REMOTE_ADDR'] = $request->getHeaderLine('REMOTE_ADDR');
            }

            $requestData['cookies'] = $request->getCookieParams();
            $requestData['headers'] = $request->getHeaders();

            $userContext = $event->getUserContext();

            if (null === $userContext->getIpAddress() && $request->hasHeader('REMOTE_ADDR')) {
                $userContext->setIpAddress($request->getHeaderLine('REMOTE_ADDR'));
            }
        } else {
            $requestData['headers'] = $self->removePiiFromHeaders($request->getHeaders());
        }

        $event->setRequest($requestData);
    }

    /**
     * Removes headers containing potential PII.
     *
     * @param array $headers Array containing request headers
     *
     * @return array
     */
    private function removePiiFromHeaders(array $headers): array
    {
        $keysToRemove = ['authorization', 'cookie', 'set-cookie', 'remote_addr'];

        return array_filter($headers, function ($key) use ($keysToRemove) {
            return !\in_array(\strtolower($key), $keysToRemove, true);
        }, ARRAY_FILTER_USE_KEY);
    }
}
