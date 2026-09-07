<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sentry\Breadcrumb;
use Sentry\DataCollection\HttpBodyCollector;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\DataCollection\KeyValueDataFilter;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

use function Sentry\getBaggage;
use function Sentry\getTraceparent;

/**
 * This handler traces each outgoing HTTP request by recording performance data.
 */
final class GuzzleTracingMiddleware
{
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

                $queryString = HttpDataCollector::collectQueryString($dataCollection, $requestUri->getQuery());
                if ($queryString !== null) {
                    $spanAndBreadcrumbData['http.query'] = $queryString;
                }
                if ($requestUri->getFragment() !== '') {
                    $spanAndBreadcrumbData['http.fragment'] = $requestUri->getFragment();
                }

                $collectedUrl = (string) $partialUri;
                if ($dataCollection !== null) {
                    $collectedUrl = HttpDataCollector::collectUrl($dataCollection, (string) $requestUri);
                    $spanAndBreadcrumbData['url.full'] = $collectedUrl;
                }

                $childSpan = null;
                $spanData = $spanAndBreadcrumbData;

                if ($parentSpan !== null && $parentSpan->getSampled()) {
                    if ($dataCollection !== null && $sdkOptions !== null) {
                        // Headers and bodies can be sizeable, so keep them on the recorded span instead of duplicating them on its breadcrumb.
                        $spanData = array_merge(
                            $spanData,
                            self::collectRequestSpanData(
                                $sdkOptions,
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

                $handlerPromiseCallback = static function ($responseOrException) use ($hub, $spanAndBreadcrumbData, $spanData, $childSpan, $parentSpan, $collectedUrl, $dataCollection, $sdkOptions) {
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
                                self::collectResponseSpanData($sdkOptions, $response)
                            );
                            $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));
                            if ($dataCollection === null) {
                                $childSpan->setData($spanData);
                            } else {
                                HttpDataCollector::setMissingSpanData($childSpan, $spanData);
                            }
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
                            'url' => $collectedUrl,
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

    /**
     * @return array<string, mixed>
     */
    private static function collectRequestSpanData(Options $options, RequestInterface $request, StreamInterface $body): array
    {
        $dataCollection = $options->getDataCollection();
        if ($dataCollection === null) {
            return [];
        }

        $data = HttpDataCollector::collectHeaders($dataCollection, $request->getHeaders(), 'request');
        $maxBodyLength = HttpBodyCollector::getMaxBodyLength($options, 'outgoingRequest');
        if ($maxBodyLength === 0) {
            return $data;
        }

        $collectedBody = self::collectBody($body, $request->getHeaderLine('Content-Type'), $maxBodyLength);

        if ($collectedBody !== null) {
            $data['http.request.body.data'] = $collectedBody;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectResponseSpanData(?Options $options, ResponseInterface $response): array
    {
        if ($options === null) {
            return [];
        }

        $dataCollection = $options->getDataCollection();
        if ($dataCollection === null) {
            return [];
        }

        $data = HttpDataCollector::collectHeaders($dataCollection, $response->getHeaders(), 'response');

        $maxBodyLength = HttpBodyCollector::getMaxBodyLength($options, 'incomingResponse');
        if ($maxBodyLength === 0) {
            return $data;
        }

        $collectedBody = self::collectBody($response->getBody(), $response->getHeaderLine('Content-Type'), $maxBodyLength);

        if ($collectedBody !== null) {
            $data['http.response.body.data'] = $collectedBody;
        }

        return $data;
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

        if (!HttpBodyCollector::isSupportedContentType($contentType)) {
            return KeyValueDataFilter::FILTERED_VALUE;
        }

        // The size can be unknown (a null body size), so readBody() enforces the limit again after reading.
        $contents = self::readBody($body, $maxBodyLength);
        if ($contents === null) {
            return null;
        }

        $parsedBody = HttpBodyCollector::parse($contents, $contentType);

        return $parsedBody === null ? KeyValueDataFilter::FILTERED_VALUE : HttpBodyCollector::collect($parsedBody);
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
