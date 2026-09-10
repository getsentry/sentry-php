<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sentry\Breadcrumb;
use Sentry\DataCollection\DataCollectionPolicy;
use Sentry\DataCollection\HttpDataCollector;
use Sentry\DataCollection\HttpHeaderNormalizer;
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
                $policy = DataCollectionPolicy::fromOptions($sdkOptions);
                $spanAndBreadcrumbData = [
                    'http.request.method' => $request->getMethod(),
                    'http.request.body.size' => $requestBody->getSize(),
                ];

                $spanAndBreadcrumbData += HttpDataCollector::collectQueryData($policy, $requestUri->getQuery());
                if ($requestUri->getFragment() !== '') {
                    $spanAndBreadcrumbData['http.fragment'] = $requestUri->getFragment();
                }

                $collectedUrl = (string) $partialUri;
                if (!$policy->isLegacyMode()) {
                    $collectedUrl = HttpDataCollector::collectUrl($policy, (string) $requestUri);
                    $spanAndBreadcrumbData['url.full'] = $collectedUrl;
                }

                $childSpan = null;
                $spanData = $spanAndBreadcrumbData;

                if ($parentSpan !== null && $parentSpan->getSampled()) {
                    if (!$policy->isLegacyMode()) {
                        // Headers and cookies can be sizeable, so keep them on the recorded span instead of duplicating them on its breadcrumb.
                        $spanData = array_merge(
                            $spanData,
                            HttpDataCollector::collectRequestData(
                                $policy,
                                HttpHeaderNormalizer::normalize($request->getHeaders())
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

                $handlerPromiseCallback = static function ($responseOrException) use ($hub, $spanAndBreadcrumbData, $spanData, $childSpan, $parentSpan, $collectedUrl, $policy) {
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
                                self::collectResponseSpanData($policy, $response)
                            );
                            $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));
                            if ($policy->isLegacyMode()) {
                                $childSpan->setData($spanData);
                            } else {
                                $childSpan->setData(array_diff_key($spanData, $childSpan->getData()));
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
    private static function collectResponseSpanData(DataCollectionPolicy $policy, ResponseInterface $response): array
    {
        return HttpDataCollector::collectResponseData(
            $policy,
            HttpHeaderNormalizer::normalize($response->getHeaders())
        );
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
