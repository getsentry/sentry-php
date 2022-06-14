<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Closure;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sentry\Breadcrumb;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

/**
 * This handler traces each outgoing HTTP request by recording performance data.
 */
final class GuzzleTracingMiddleware
{
    public static function trace(?HubInterface $hub = null): Closure
    {
        return static function (callable $handler) use ($hub): Closure {
            return static function (RequestInterface $request, array $options) use ($hub, $handler) {
                $hub = $hub ?? SentrySdk::getCurrentHub();
                $span = $hub->getSpan();

                if (null === $span) {
                    return $handler($request, $options);
                }

                $spanContext = new SpanContext();
                $spanContext->setOp('http.client');
                $spanContext->setDescription($request->getMethod() . ' ' . $request->getUri());

                $childSpan = $span->startChild($spanContext);

                $request = $request->withHeader('sentry-trace', $childSpan->toTraceparent());

                $handlerPromiseCallback = static function ($responseOrException) use ($hub, $request, $childSpan) {
                    // We finish the span (which means setting the span end timestamp) first to ensure the measured time
                    // the span spans is as close to only the HTTP request time and do the data collection afterwards
                    $childSpan->finish();

                    $response = null;

                    /** @psalm-suppress UndefinedClass */
                    if ($responseOrException instanceof ResponseInterface) {
                        $response = $responseOrException;
                    } elseif ($responseOrException instanceof GuzzleRequestException) {
                        $response = $responseOrException->getResponse();
                    }

                    $breadcrumbData = [
                        'url' => (string) $request->getUri(),
                        'method' => $request->getMethod(),
                        'request_body_size' => $request->getBody()->getSize(),
                    ];

                    if (null !== $response) {
                        $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($response->getStatusCode()));

                        $breadcrumbData['status_code'] = $response->getStatusCode();
                        $breadcrumbData['response_body_size'] = $response->getBody()->getSize();
                    } else {
                        $childSpan->setStatus(SpanStatus::internalError());
                    }

                    $hub->addBreadcrumb(new Breadcrumb(
                        Breadcrumb::LEVEL_INFO,
                        Breadcrumb::TYPE_HTTP,
                        'http',
                        null,
                        $breadcrumbData
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
}
