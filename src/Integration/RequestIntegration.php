<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpCookieCollector;
use Sentry\DataCollection\HttpHeaderCollector;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpUrlCollector;
use Sentry\Event;
use Sentry\Options;
use Sentry\OptionsResolver;
use Sentry\SentrySdk;
use Sentry\State\Scope;
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
     * @var array<string, mixed> The explicitly configured options
     *
     * @phpstan-var array{
     *     pii_sanitize_headers?: string[]
     * }
     */
    private $options;

    /**
     * Constructor.
     *
     * @param RequestFetcherInterface|null $requestFetcher PSR-7 request fetcher
     * @param array<string, mixed>         $options        The options
     *
     * @phpstan-param array{
     *     pii_sanitize_headers?: string[]
     * } $options
     */
    public function __construct(?RequestFetcherInterface $requestFetcher = null, array $options = [])
    {
        $resolver = new OptionsResolver();

        $this->configureOptions($resolver);

        $this->requestFetcher = $requestFetcher ?? new RequestFetcher();

        /** @var array{pii_sanitize_headers?: string[]} $resolvedOptions */
        $resolvedOptions = $resolver->resolveOnly($options);
        $this->options = $resolvedOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            $currentHub = SentrySdk::getCurrentHub();
            $integration = $currentHub->getIntegration(self::class);
            $client = $currentHub->getClient();

            // The client bound to the current hub, if any, could not have this
            // integration enabled. If this is the case, bail out
            if ($integration === null || $client === null) {
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

        $policy = DataCollectionPolicy::fromOptions($options);
        $uri = $request->getUri();

        $requestData = [
            'url' => HttpUrlCollector::collect($policy, HttpMessageType::incomingRequest(), $uri),
            'method' => $request->getMethod(),
        ];

        $queryString = HttpUrlCollector::collectQueryString($policy, $uri->getQuery());
        if ($queryString !== null) {
            $requestData['query_string'] = $queryString;
        }

        $serverParams = $request->getServerParams();
        if (!empty($serverParams['REMOTE_ADDR']) && $policy->shouldCollectUserInfo()) {
            /** @var string $ipAddress */
            $ipAddress = $serverParams['REMOTE_ADDR'];
            $this->addRequestUserInfo($event, $ipAddress, $requestData);
        }

        $cookies = HttpCookieCollector::collect($policy, HttpMessageType::incomingRequest(), $request->getCookieParams());
        if ($cookies !== null) {
            $requestData['cookies'] = $cookies;
        }

        $headers = HttpHeaderCollector::collect(
            $policy,
            HttpMessageType::incomingRequest(),
            $request->getHeaders(),
            $this->options['pii_sanitize_headers'] ?? null
        );
        if ($headers !== null) {
            $requestData['headers'] = $headers;
        }

        if (!\array_key_exists('data', $event->getRequest())) {
            $requestBody = HttpBodyCollector::collectServerRequest($policy, $request);
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

    /**
     * Configures the options of the client.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setAllowedTypes('pii_sanitize_headers', 'string[]');
        $resolver->setNormalizer('pii_sanitize_headers', static function (array $value): array {
            return array_map('strtolower', $value);
        });
        $resolver->setDefault('pii_sanitize_headers', HttpHeaderCollector::DEFAULT_PII_SANITIZE_HEADERS);
    }
}
