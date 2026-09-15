<?php

declare(strict_types=1);

namespace Sentry\Integration;

use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\DataCollection\RequestDataCollector;
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
     * @var array<string, mixed> The options
     *
     * @phpstan-var array{
     *     pii_sanitize_headers: string[]
     * }
     */
    private $options;

    /**
     * @var bool Whether the application explicitly supplied header restrictions
     */
    private $hasConfiguredSanitizeHeaders;

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
        $this->hasConfiguredSanitizeHeaders = \array_key_exists('pii_sanitize_headers', $options);

        /** @var array{pii_sanitize_headers: string[]} $resolvedOptions */
        $resolvedOptions = $resolver->resolve($options);
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
        $collector = new RequestDataCollector(
            $policy,
            $this->hasConfiguredSanitizeHeaders ? $this->options['pii_sanitize_headers'] : null
        );
        $queryString = $collector->collectQueryString($request->getUri()->getQuery());

        $requestData = [
            'url' => HttpDataCollector::collectUrl($policy, (string) $request->getUri()),
            'method' => $request->getMethod(),
        ];

        if ($queryString !== null) {
            $requestData['query_string'] = $queryString;
        }

        $serverParams = $request->getServerParams();
        if (!empty($serverParams['REMOTE_ADDR'])) {
            /** @var string $ipAddress */
            $ipAddress = $serverParams['REMOTE_ADDR'];
            $userData = $collector->collectUserInfo(['ip_address' => $ipAddress]);
            if ($userData !== []) {
                $this->addRequestUserInfo($event, $userData, $requestData);
            }
        }

        $cookies = $collector->collectCookies($request->getCookieParams());

        if ($cookies !== null) {
            $requestData['cookies'] = $cookies;
        }

        $headers = $collector->collectHeaders($request->getHeaders());
        $cookieFallback = $collector->collectMalformedCookieHeader($request->getHeader('Cookie'));

        if ($headers !== null || $cookieFallback !== []) {
            $requestData['headers'] = ($headers ?? []) + $cookieFallback;
        }

        if (!\array_key_exists('data', $event->getRequest())) {
            $requestBody = HttpBodyCollector::collectServerRequest($policy, $request);
            if ($requestBody !== null) {
                $requestData['data'] = $requestBody;
            }
        }

        // Explicit request fields take precedence, including null and empty values.
        $event->setRequest($event->getRequest() + $requestData);
    }

    /**
     * @param array<string, string> $userData
     * @param array<string, mixed>  $requestData
     */
    private function addRequestUserInfo(Event $event, array $userData, array &$requestData): void
    {
        $user = $event->getUser();
        $requestData['env'] = ['REMOTE_ADDR' => $userData['ip_address']];

        if ($user === null) {
            $user = UserDataBag::createFromUserIpAddress($userData['ip_address']);
        } elseif ($user->getIpAddress() === null) {
            $user->setIpAddress($userData['ip_address']);
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
        $resolver->setDefault('pii_sanitize_headers', RequestDataCollector::DEFAULT_PII_SANITIZE_HEADERS);
    }
}
