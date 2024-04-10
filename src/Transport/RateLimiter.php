<?php

declare(strict_types=1);

namespace Sentry\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sentry\EventType;
use Sentry\HttpClient\Response;

final class RateLimiter
{
    /**
     * @var string
     */
    private const DATA_CATEGORY_ERROR = 'error';

    /**
     * @var string
     */
    private const DATA_CATEGORY_METRIC_BUCKET = 'metric_bucket';

    /**
     * The name of the header to look at to know the rate limits for the events
     * categories supported by the server.
     */
    private const RATE_LIMITS_HEADER = 'X-Sentry-Rate-Limits';

    /**
     * The name of the header to look at to know after how many seconds the HTTP
     * request should be retried.
     */
    private const RETRY_AFTER_HEADER = 'Retry-After';

    /**
     * The number of seconds after which an HTTP request can be retried.
     */
    private const DEFAULT_RETRY_AFTER_SECONDS = 60;

    /**
     * @var array<string, int> The map of time instants for each event category after
     *                         which an HTTP request can be retried
     */
    private $rateLimits = [];

    /**
     * @var LoggerInterface A PSR-3 logger
     */
    private $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function handleResponse(Response $response): bool
    {
        $now = time();

        if ($response->hasHeader(self::RATE_LIMITS_HEADER)) {
            foreach (explode(',', $response->getHeaderLine(self::RATE_LIMITS_HEADER)) as $limit) {
                /**
                 * $parameters[0] - retry_after
                 * $parameters[1] - categories
                 * $parameters[2] - scope (not used)
                 * $parameters[3] - reason_code (not used)
                 * $parameters[4] - namespaces (only returned if categories contains "metric_bucket").
                 */
                $parameters = explode(':', $limit, 5);

                $retryAfter = $now + (ctype_digit($parameters[0]) ? (int) $parameters[0] : self::DEFAULT_RETRY_AFTER_SECONDS);

                foreach (explode(';', $parameters[1]) as $category) {
                    switch ($category) {
                        case self::DATA_CATEGORY_METRIC_BUCKET:
                            $namespaces = [];
                            if (isset($parameters[4])) {
                                $namespaces = explode(';', $parameters[4]);
                            }

                            /**
                             * As we do not support setting any metric namespaces in the SDK,
                             * checking for the custom namespace suffices.
                             * In case the namespace was ommited in the response header,
                             * we'll also back off.
                             */
                            if ($namespaces === [] || \in_array('custom', $namespaces)) {
                                $this->rateLimits[self::DATA_CATEGORY_METRIC_BUCKET] = $retryAfter;
                            }
                            break;
                        default:
                            $this->rateLimits[$category ?: 'all'] = $retryAfter;
                    }

                    $this->logger->warning(
                        sprintf('Rate limited exceeded for category "%s", backing off until "%s".', $category, gmdate(\DATE_ATOM, $retryAfter))
                    );
                }
            }

            return $this->rateLimits !== [];
        }

        if ($response->hasHeader(self::RETRY_AFTER_HEADER)) {
            $retryAfter = $now + $this->parseRetryAfterHeader($now, $response->getHeaderLine(self::RETRY_AFTER_HEADER));

            $this->rateLimits['all'] = $retryAfter;

            $this->logger->warning(
                sprintf('Rate limited exceeded for all categories, backing off until "%s".', gmdate(\DATE_ATOM, $retryAfter))
            );

            return true;
        }

        return false;
    }

    public function isRateLimited(EventType $eventType): bool
    {
        $disabledUntil = $this->getDisabledUntil($eventType);

        return $disabledUntil > time();
    }

    public function getDisabledUntil(EventType $eventType): int
    {
        $category = (string) $eventType;

        if ($eventType === EventType::event()) {
            $category = self::DATA_CATEGORY_ERROR;
        }

        if ($eventType === EventType::metrics()) {
            $category = self::DATA_CATEGORY_METRIC_BUCKET;
        }

        return max($this->rateLimits['all'] ?? 0, $this->rateLimits[$category] ?? 0);
    }

    private function parseRetryAfterHeader(int $currentTime, string $header): int
    {
        if (preg_match('/^\d+$/', $header) === 1) {
            return (int) $header;
        }

        $headerDate = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::RFC1123, $header);

        if ($headerDate !== false && $headerDate->getTimestamp() >= $currentTime) {
            return $headerDate->getTimestamp() - $currentTime;
        }

        return self::DEFAULT_RETRY_AFTER_SECONDS;
    }
}
