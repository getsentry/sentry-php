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
    public static function trace(?HubInterface $hub = null): \Closure
    {
        return function (callable $handler) use ($hub): \Closure {
            return function (RequestInterface $request, array $options) use ($hub, $handler) {
                $hub = $hub ?? SentrySdk::getCurrentHub();
                $transaction = $hub->getTransaction();
                $span = null;

                if (null !== $transaction) {
                    $spanContext = new SpanContext();
                    $spanContext->setOp('http.guzzle');
                    $spanContext->setDescription($request->getMethod() . ' ' . $request->getUri());

                    $span = $transaction->startChild($spanContext);
                }

                $handlerPromiseCallback = static function ($responseOrException) use ($span) {
                    if (null !== $span) {
                        $span->finish();
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
