<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sentry\Breadcrumb;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpSpanDataCollector;
use Sentry\DataCollection\HttpUrlCollector;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\BreadcrumbRecorder;
use Sentry\State\IsolationScope;

use function Sentry\getBaggage;
use function Sentry\getTraceparent;

/**
 * This handler traces each outgoing HTTP request by recording performance data.
 */
final class GuzzleTracingMiddleware
{
    public static function trace(?IsolationScope $scope = null): \Closure
    {
        return static function (callable $handler) use ($scope): \Closure {
            return static function (RequestInterface $request, array $options) use ($handler, $scope) {
                $scope = $scope ?? SentrySdk::getIsolationScope();
                $client = SentrySdk::getClient($scope);
                $parentSpan = $scope->getSpan();
                $requestUri = $request->getUri();

                $partialUri = Uri::fromParts([
                    'scheme' => $requestUri->getScheme(),
                    'host' => $requestUri->getHost(),
                    'port' => $requestUri->getPort(),
                    'path' => $requestUri->getPath(),
                ]);

                $sdkOptions = $client->getOptions();
                $spanAndBreadcrumbData = [
                    'http.request.method' => $request->getMethod(),
                    'http.request.body.size' => $request->getBody()->getSize(),
                ];

                $queryString = HttpUrlCollector::collectQueryString($sdkOptions, $requestUri->getQuery());
                if ($queryString !== null) {
                    $spanAndBreadcrumbData['http.query'] = $queryString;
                }

                if ($requestUri->getFragment() !== '') {
                    $spanAndBreadcrumbData['http.fragment'] = $requestUri->getFragment();
                }

                $fullUrl = HttpUrlCollector::collect($sdkOptions, HttpMessageType::outgoingRequest(), $requestUri);
                if ($fullUrl !== null) {
                    $spanAndBreadcrumbData['url.full'] = $fullUrl;
                }

                $breadcrumbUrl = $fullUrl ?? (string) $partialUri;
                $childSpan = null;

                if ($parentSpan !== null && $parentSpan->getSampled()) {
                    $spanData = array_merge($spanAndBreadcrumbData, HttpSpanDataCollector::collectPsr7Request($sdkOptions, HttpMessageType::outgoingRequest(), $request));

                    $spanContext = new SpanContext();
                    $spanContext->setOp('http.client');
                    $spanContext->setData($spanData);
                    $spanContext->setOrigin('auto.http.guzzle');
                    $spanContext->setDescription($request->getMethod() . ' ' . $partialUri);

                    $childSpan = $parentSpan->startChild($spanContext);

                    $scope->setSpan($childSpan);
                }

                if (self::shouldAttachTracingHeaders($sdkOptions, $request)) {
                    $traceParent = getTraceparent($scope);
                    if ($traceParent !== '') {
                        $request = $request->withHeader('sentry-trace', $traceParent);
                    }

                    $baggage = getBaggage($scope);
                    if ($baggage !== '') {
                        $request = $request->withHeader('baggage', $baggage);
                    }
                }

                $handlerPromiseCallback = static function ($responseOrException) use ($client, $scope, $spanAndBreadcrumbData, $childSpan, $breadcrumbUrl, $sdkOptions) {
                    if ($childSpan !== null) {
                        // We finish the span first to keep the measured duration as close as possible to the HTTP request time.
                        $childSpan->finish();
                    }

                    $response = null;

                    if ($responseOrException instanceof ResponseInterface) {
                        $response = $responseOrException;
                    } elseif ($responseOrException instanceof GuzzleRequestException && method_exists($responseOrException, 'getResponse')) {
                        $response = $responseOrException->getResponse();
                    }

                    $breadcrumbLevel = Breadcrumb::LEVEL_INFO;

                    if ($response instanceof ResponseInterface) {
                        $statusCode = $response->getStatusCode();
                        $spanAndBreadcrumbData['http.response.body.size'] = $response->getBody()->getSize();
                        $spanAndBreadcrumbData['http.response.status_code'] = $statusCode;

                        if ($statusCode >= 400 && $statusCode < 500) {
                            $breadcrumbLevel = Breadcrumb::LEVEL_WARNING;
                        } elseif ($statusCode >= 500) {
                            $breadcrumbLevel = Breadcrumb::LEVEL_ERROR;
                        }
                    }

                    if ($childSpan !== null) {
                        if ($response instanceof ResponseInterface) {
                            $spanData = array_merge($spanAndBreadcrumbData, HttpSpanDataCollector::collectPsr7Response($sdkOptions, HttpMessageType::incomingResponse(), $response));

                            $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));
                            $childSpan->setData(array_merge($spanData, $childSpan->getData()));
                        } else {
                            $childSpan->setStatus(SpanStatus::internalError());
                        }
                    }

                    BreadcrumbRecorder::record($client, $scope, new Breadcrumb(
                        $breadcrumbLevel,
                        Breadcrumb::TYPE_HTTP,
                        'http',
                        null,
                        array_merge([
                            'url' => $breadcrumbUrl,
                        ], $spanAndBreadcrumbData)
                    ));

                    if ($responseOrException instanceof \Throwable) {
                        throw $responseOrException;
                    }

                    return $responseOrException;
                };

                try {
                    /** @var PromiseInterface $promise */
                    $promise = $handler($request, $options);
                } finally {
                    // The span is the current span only while the next handlers run, so that their spans
                    // are its children. The promise can resolve after other requests started (e.g.
                    // concurrent requests): those must not become children of this span
                    if ($childSpan !== null) {
                        $scope->setSpan($parentSpan);
                    }
                }

                return $promise->then($handlerPromiseCallback, $handlerPromiseCallback);
            };
        };
    }

    private static function shouldAttachTracingHeaders(Options $options, RequestInterface $request): bool
    {
        // Check if the request destination is allow listed in the trace_propagation_targets option.
        return $options->getTracePropagationTargets() === null
               || \in_array($request->getUri()->getHost(), $options->getTracePropagationTargets());
    }
}
