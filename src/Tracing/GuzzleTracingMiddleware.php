<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Psr\Http\Message\RequestInterface;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

/**
 * This handler traces each outgoing HTTP request by recording performance data.
 */
final class GuzzleTracingMiddleware
{
    public static function trace(?HubInterface $hub = null, bool $attachHeader = false): \Closure
    {
        return function (callable $handler) use ($attachHeader, $hub): \Closure {
            return function (RequestInterface $request, array $options) use ($attachHeader, $hub, $handler) {
                $hub = $hub ?? SentrySdk::getCurrentHub();
                $span = $hub->getSpan();
                $childSpan = null;

                if (null !== $span) {
                    $spanContext = new SpanContext();
                    $spanContext->setOp('http.guzzle');
                    $spanContext->setDescription($request->getMethod() . ' ' . $request->getUri());

                    $childSpan = $span->startChild($spanContext);
                    
                    if ($attachHeader) {
                        $request = $request->withAddedHeader('sentry-trace', $childSpan->toTraceparent());
                    }
                }

                $handlerPromiseCallback = static function ($responseOrException) use ($childSpan) {
                    if (null !== $childSpan) {
                        $childSpan->finish();
                    }

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
