<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpCookieCollector;
use Sentry\DataCollection\HttpHeaderCollector;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpUrlCollector;
use Sentry\Event;
use Sentry\EventType;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\UserDataBag;

/**
 * This integration collects information from the request and attaches them to
 * the event.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class RequestIntegration implements IntegrationInterface
{
    /**
     * @var RequestFetcherInterface PSR-7 request fetcher
     */
    private $requestFetcher;

    /**
     * Constructor.
     *
     * @param RequestFetcherInterface|null $requestFetcher PSR-7 request fetcher
     */
    public function __construct(?RequestFetcherInterface $requestFetcher = null)
    {
        $this->requestFetcher = $requestFetcher ?? new RequestFetcher();
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        SentrySdk::getGlobalScope()->addEventProcessor(function (Event $event): Event {
            $client = SentrySdk::getClient();
            $integration = $client->getIntegration(self::class);

            if ($integration === null) {
                return $event;
            }

            $this->processEvent($event, $client->getOptions());

            return $event;
        });
    }

    private function processEvent(Event $event, Options $options): void
    {
        $request = $this->requestFetcher->fetchRequest();

        if ($request === null) {
            return;
        }

        $uri = $request->getUri();

        $requestData = [
            'url' => HttpUrlCollector::collect($options, $uri),
            'method' => $request->getMethod(),
        ];

        $queryString = HttpUrlCollector::collectQueryString($options, $uri->getQuery());
        if ($queryString !== null) {
            $requestData['query_string'] = $queryString;
        }

        $serverParams = $request->getServerParams();
        if (!empty($serverParams['REMOTE_ADDR']) && $options->getDataCollection()->shouldCollectUserInfo()) {
            /** @var string $ipAddress */
            $ipAddress = $serverParams['REMOTE_ADDR'];
            $this->addRequestUserInfo($event, $ipAddress, $requestData);
        }

        $cookies = HttpCookieCollector::collect($options, $request->getCookieParams());
        if ($cookies !== null) {
            $requestData['cookies'] = $cookies;
        }

        $headers = HttpHeaderCollector::collect($options, HttpMessageType::incomingRequest(), $request->getHeaders());
        if ($headers !== null) {
            $requestData['headers'] = $headers;
        }

        // Request bodies attached here are only relevant for error events, other events would
        // needlessly read the body only to be discarded later on
        if ($event->getType() === EventType::event() && !\array_key_exists('data', $event->getRequest())) {
            $requestBody = HttpBodyCollector::collectServerRequest($options, $request);
            if ($requestBody !== null) {
                $requestData['data'] = $requestBody;
            }
        }

        $event->setRequest($event->getRequest() + $requestData);
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function addRequestUserInfo(Event $event, string $ipAddress, array &$requestData): void
    {
        $user = $event->getUser();
        $requestData['env'] = ['REMOTE_ADDR' => $ipAddress];

        if ($user === null) {
            $user = UserDataBag::createFromUserIpAddress($ipAddress);
        } elseif ($user->getIpAddress() === null) {
            $user->setIpAddress($ipAddress);
        }

        $event->setUser($user);
    }
}
