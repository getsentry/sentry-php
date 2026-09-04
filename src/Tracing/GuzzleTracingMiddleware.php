<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sentry\Breadcrumb;
use Sentry\DataCollection\DataCollectionOptions;
use Sentry\DataCollection\KeyValueDataFilter;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Util\JSON;

use function Sentry\getBaggage;
use function Sentry\getTraceparent;

/**
 * This handler traces each outgoing HTTP request by recording performance data.
 */
final class GuzzleTracingMiddleware
{
    // Avoid reading arbitrarily large or unknown-sized streams into memory.
    private const HTTP_BODY_MAX_CONTENT_LENGTH = 10 ** 5;

    private const MAX_REQUEST_BODY_SIZE_TO_LENGTH = [
        'none' => 0,
        'never' => 0,
        'small' => 10 ** 3,
        'medium' => 10 ** 4,
        'always' => self::HTTP_BODY_MAX_CONTENT_LENGTH,
    ];

    public static function trace(?HubInterface $hub = null): \Closure
    {
        return static function (callable $handler) use ($hub): \Closure {
            return static function (RequestInterface $request, array $options) use ($hub, $handler) {
                $hub = $hub ?? SentrySdk::getCurrentHub();
                $client = $hub->getClient();
                $parentSpan = $hub->getSpan();
                $requestUri = $request->getUri();
                $requestBody = $request->getBody();

                $partialUri = Uri::fromParts([
                    'scheme' => $requestUri->getScheme(),
                    'host' => $requestUri->getHost(),
                    'port' => $requestUri->getPort(),
                    'path' => $requestUri->getPath(),
                ]);

                $sdkOptions = $client !== null ? $client->getOptions() : null;
                $dataCollection = $sdkOptions !== null ? $sdkOptions->getDataCollection() : null;
                $spanAndBreadcrumbData = [
                    'http.request.method' => $request->getMethod(),
                    'http.request.body.size' => $requestBody->getSize(),
                ];

                $queryString = self::collectQueryString($dataCollection, $requestUri->getQuery());
                if ($queryString !== null) {
                    $spanAndBreadcrumbData['http.query'] = $queryString;
                }
                if ($requestUri->getFragment() !== '') {
                    $spanAndBreadcrumbData['http.fragment'] = $requestUri->getFragment();
                }

                $collectedUri = $partialUri;
                if ($dataCollection !== null) {
                    $collectedUri = $collectedUri
                        ->withQuery($queryString ?? '')
                        ->withFragment($requestUri->getFragment());
                    $spanAndBreadcrumbData['url.full'] = (string) $collectedUri;
                }

                $childSpan = null;
                $spanData = $spanAndBreadcrumbData;

                if ($parentSpan !== null && $parentSpan->getSampled()) {
                    if ($dataCollection !== null && $sdkOptions !== null) {
                        // Headers and bodies can be sizeable, so keep them on the recorded span instead of duplicating them on its breadcrumb.
                        $spanData = array_merge(
                            $spanData,
                            self::collectRequestSpanData(
                                $dataCollection,
                                $sdkOptions->getMaxRequestBodySize(),
                                $request,
                                $requestBody
                            )
                        );
                    }

                    $spanContext = new SpanContext();
                    $spanContext->setOp('http.client');
                    $spanContext->setData($spanData);
                    $spanContext->setOrigin('auto.http.guzzle');
                    $spanContext->setDescription($request->getMethod() . ' ' . $partialUri);

                    $childSpan = $parentSpan->startChild($spanContext);

                    $hub->setSpan($childSpan);
                }

                if (self::shouldAttachTracingHeaders($sdkOptions, $request)) {
                    $traceParent = getTraceparent();
                    if ($traceParent !== '') {
                        $request = $request->withHeader('sentry-trace', $traceParent);
                    }

                    $baggage = getBaggage();
                    if ($baggage !== '') {
                        $request = $request->withHeader('baggage', $baggage);
                    }
                }

                $handlerPromiseCallback = static function ($responseOrException) use ($hub, $spanAndBreadcrumbData, $spanData, $childSpan, $parentSpan, $collectedUri, $dataCollection) {
                    if ($childSpan !== null) {
                        // We finish the span (which means setting the span end timestamp) first to ensure the measured time
                        // the span spans is as close to only the HTTP request time and do the data collection afterwards
                        $childSpan->finish();

                        $hub->setSpan($parentSpan);
                    }

                    /** @var ResponseInterface|null $response */
                    $response = null;

                    if ($responseOrException instanceof ResponseInterface) {
                        $response = $responseOrException;
                    } elseif ($responseOrException instanceof GuzzleRequestException && method_exists($responseOrException, 'getResponse')) {
                        $response = $responseOrException->getResponse();
                    }

                    $breadcrumbLevel = Breadcrumb::LEVEL_INFO;

                    if ($response instanceof ResponseInterface) {
                        $responseBody = $response->getBody();
                        $statusCode = $response->getStatusCode();
                        $spanAndBreadcrumbData['http.response.body.size'] = $responseBody->getSize();
                        $spanAndBreadcrumbData['http.response.status_code'] = $statusCode;

                        if ($statusCode >= 400 && $statusCode < 500) {
                            $breadcrumbLevel = Breadcrumb::LEVEL_WARNING;
                        } elseif ($statusCode >= 500) {
                            $breadcrumbLevel = Breadcrumb::LEVEL_ERROR;
                        }
                    }

                    if ($childSpan !== null) {
                        if ($response instanceof ResponseInterface) {
                            $spanData = array_merge(
                                $spanData,
                                $spanAndBreadcrumbData,
                                self::collectResponseSpanData($dataCollection, $response)
                            );
                            $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));
                            $childSpan->setData($spanData);
                        } else {
                            $childSpan->setStatus(SpanStatus::internalError());
                        }
                    }

                    $hub->addBreadcrumb(new Breadcrumb(
                        $breadcrumbLevel,
                        Breadcrumb::TYPE_HTTP,
                        'http',
                        null,
                        array_merge([
                            'url' => (string) $collectedUri,
                        ], $spanAndBreadcrumbData)
                    ));

                    if ($responseOrException instanceof \Throwable) {
                        throw $responseOrException;
                    }

                    return $responseOrException;
                };

                return $handler($request, $options)->then($handlerPromiseCallback, $handlerPromiseCallback);
            };
        };
    }

    private static function collectQueryString(?DataCollectionOptions $dataCollection, string $queryString): ?string
    {
        if ($queryString === '') {
            return null;
        }

        if ($dataCollection === null) {
            return $queryString;
        }

        return KeyValueDataFilter::filterQueryString($queryString, $dataCollection->getUrlQueryParams());
    }

    /**
     * @param 'none'|'never'|'small'|'medium'|'always' $maxRequestBodySize
     *
     * @return array<string, mixed>
     */
    private static function collectRequestSpanData(
        DataCollectionOptions $dataCollection,
        string $maxRequestBodySize,
        RequestInterface $request,
        StreamInterface $body
    ): array {
        $data = self::collectHeaders($dataCollection, $request->getHeaders(), 'request');

        if (!\in_array('outgoingRequest', $dataCollection->getHttpBodies(), true)) {
            return $data;
        }

        $maxBodyLength = self::MAX_REQUEST_BODY_SIZE_TO_LENGTH[$maxRequestBodySize];
        $collectedBody = self::collectBody($body, $request->getHeaderLine('Content-Type'), $maxBodyLength);

        if ($collectedBody !== null) {
            $data['http.request.body.data'] = $collectedBody;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectResponseSpanData(?DataCollectionOptions $dataCollection, ResponseInterface $response): array
    {
        if ($dataCollection === null) {
            return [];
        }

        $data = self::collectHeaders($dataCollection, $response->getHeaders(), 'response');

        if (!\in_array('incomingResponse', $dataCollection->getHttpBodies(), true)) {
            return $data;
        }

        $collectedBody = self::collectBody(
            $response->getBody(),
            $response->getHeaderLine('Content-Type'),
            self::HTTP_BODY_MAX_CONTENT_LENGTH
        );

        if ($collectedBody !== null) {
            $data['http.response.body.data'] = $collectedBody;
        }

        return $data;
    }

    /**
     * @param array<array-key, string[]> $headers
     * @param 'request'|'response'       $direction
     *
     * @return array<string, string[]>
     */
    private static function collectHeaders(DataCollectionOptions $dataCollection, array $headers, string $direction): array
    {
        $headerBehavior = $dataCollection->getHttpHeaders()[$direction];
        $cookieBehavior = $dataCollection->getCookies();
        $prefix = 'http.' . $direction . '.header.';
        $regularHeaders = [];
        $attributes = [];

        foreach ($headers as $name => $values) {
            $name = strtolower((string) $name);

            if ($name === 'cookie' || $name === 'set-cookie') {
                if ($cookieBehavior['mode'] !== 'off' && $values !== []) {
                    // PSR-7 exposes cookies as raw header strings, so use the safe fallback required by the data collection spec.
                    $attributes[$prefix . $name] = array_fill(0, \count($values), KeyValueDataFilter::FILTERED_VALUE);
                }

                continue;
            }

            $regularHeaders[$name] = $values;
        }

        $filteredHeaders = KeyValueDataFilter::filterHeaders($regularHeaders, $headerBehavior);
        foreach ($filteredHeaders ?? [] as $name => $values) {
            $attributes[$prefix . $name] = $values;
        }

        return $attributes;
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    private static function collectBody(StreamInterface $body, string $contentType, int $maxBodyLength)
    {
        if ($maxBodyLength === 0) {
            return null;
        }

        $bodySize = $body->getSize();
        if ($bodySize === 0 || ($bodySize !== null && $bodySize > $maxBodyLength)) {
            return null;
        }

        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        $isJson = $mediaType === 'application/json'
            // RFC 6839 structured syntax suffix, e.g. application/problem+json.
            || substr($mediaType, -5) === '+json';
        $isForm = $mediaType === 'application/x-www-form-urlencoded';

        if (!$isJson && !$isForm) {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        // The size can be unknown (a null body size), so readBody() enforces the limit again after reading.
        $bodyContents = self::readBody($body, $maxBodyLength);
        if ($bodyContents === null) {
            return null;
        }

        try {
            if ($isJson) {
                /** @mago-ignore analysis:mixed-assignment */
                $decodedBody = JSON::decode($bodyContents);
            } else {
                /** @var array<string, mixed> $decodedBody */
                $decodedBody = Query::parse($bodyContents);
            }
        } catch (\Throwable $exception) {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        if (!\is_array($decodedBody)) {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        return KeyValueDataFilter::filterHttpBodyData($decodedBody);
    }

    private static function readBody(StreamInterface $body, int $maxBodyLength): ?string
    {
        if (!$body->isReadable() || !$body->isSeekable()) {
            return null;
        }

        $position = null;

        try {
            $position = $body->tell();
            $body->rewind();

            // Read one byte past the limit to detect bodies of unknown size that exceed it.
            $contents = Utils::copyToString($body, $maxBodyLength + 1);

            if ($contents === '' || \strlen($contents) > $maxBodyLength) {
                return null;
            }

            return $contents;
        } catch (\Throwable $exception) {
            return null;
        } finally {
            if ($position !== null) {
                self::restoreBodyPosition($body, $position);
            }
        }
    }

    private static function restoreBodyPosition(StreamInterface $body, int $position): void
    {
        try {
            $body->seek($position);
        } catch (\Throwable $exception) {
            // Ignore streams that report themselves as seekable but cannot be restored.
        }
    }

    private static function shouldAttachTracingHeaders(?Options $options, RequestInterface $request): bool
    {
        if ($options === null) {
            return false;
        }

        // Check if the request destination is allow listed in the trace_propagation_targets option.
        return $options->getTracePropagationTargets() === null
               || \in_array($request->getUri()->getHost(), $options->getTracePropagationTargets());
    }
}
