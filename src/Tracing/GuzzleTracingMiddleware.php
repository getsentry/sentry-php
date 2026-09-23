<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sentry\Breadcrumb;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpCookieCollector;
use Sentry\DataCollection\HttpHeaderCollector;
use Sentry\DataCollection\HttpMessageType;
use Sentry\DataCollection\HttpUrlCollector;
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

                $partialUri = Uri::fromParts([
                    'scheme' => $requestUri->getScheme(),
                    'host' => $requestUri->getHost(),
                    'port' => $requestUri->getPort(),
                    'path' => $requestUri->getPath(),
                ]);

                $sdkOptions = $client !== null ? $client->getOptions() : null;
                $policy = DataCollectionPolicy::fromOptions($sdkOptions);
                $spanAndBreadcrumbData = [
                    'http.request.method' => $request->getMethod(),
                    'http.request.body.size' => $request->getBody()->getSize(),
                ];

                $queryString = HttpUrlCollector::collectQueryString($policy, $requestUri->getQuery());
                if ($queryString !== null) {
                    $spanAndBreadcrumbData['http.query'] = $queryString;
                }

                if ($requestUri->getFragment() !== '') {
                    $spanAndBreadcrumbData['http.fragment'] = $requestUri->getFragment();
                }

                $fullUrl = HttpUrlCollector::collect($policy, HttpMessageType::outgoingRequest(), $requestUri);
                if ($fullUrl !== null) {
                    $spanAndBreadcrumbData['url.full'] = $fullUrl;
                }

                $breadcrumbUrl = $fullUrl ?? (string) $partialUri;
                $childSpan = null;

                if ($parentSpan !== null && $parentSpan->getSampled()) {
                    $spanData = $spanAndBreadcrumbData;
                    self::addHeaderData($spanData, 'http.request.header', HttpHeaderCollector::collect($policy, HttpMessageType::outgoingRequest(), $request->getHeaders()));
                    self::addCookieData($spanData, 'http.request.header.cookie', HttpCookieCollector::collectPsr7Request($policy, HttpMessageType::outgoingRequest(), $request));

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

                $handlerPromiseCallback = static function ($responseOrException) use ($hub, $spanAndBreadcrumbData, $childSpan, $parentSpan, $breadcrumbUrl, $policy) {
                    if ($childSpan !== null) {
                        // We finish the span (which means setting the span end timestamp) first to ensure the measured time
                        // the span spans is as close to only the HTTP request time and do the data collection afterwards
                        $childSpan->finish();

                        $hub->setSpan($parentSpan);
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
                            $spanData = $spanAndBreadcrumbData;
                            self::addHeaderData($spanData, 'http.response.header', HttpHeaderCollector::collect($policy, HttpMessageType::incomingResponse(), $response->getHeaders()));
                            self::addCookieData($spanData, 'http.response.header.set_cookie', HttpCookieCollector::collectPsr7Response($policy, HttpMessageType::incomingResponse(), $response));

                            $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));
                            $childSpan->setData(array_merge($spanData, $childSpan->getData()));
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
                            'url' => $breadcrumbUrl,
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
     * @param array<string, mixed>            $data
     * @param array<array-key, string[]>|null $headers
     */
    private static function addHeaderData(array &$data, string $prefix, ?array $headers): void
    {
        foreach ($headers ?? [] as $name => $values) {
            $data[$prefix . '.' . strtolower((string) $name)] = $values;
        }
    }

    /**
     * @param array<string, mixed>                $data
     * @param array<array-key, mixed>|string|null $cookies Cookies grouped by name, or `[Filtered]` if they could not be parsed
     */
    private static function addCookieData(array &$data, string $prefix, $cookies): void
    {
        if (\is_string($cookies)) {
            $data[$prefix] = $cookies;

            return;
        }

        /** @mago-ignore analysis:mixed-assignment */
        foreach ($cookies ?? [] as $name => $value) {
            $data[$prefix . '.' . $name] = $value;
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
