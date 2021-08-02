<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Closure;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
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
                $spanContext->setData([
                    'url' => (string) $request->getUri(),
                    'method' => $request->getMethod(),
                    'request_body_size' => $request->getBody()->getSize(),
                ]);
                $spanContext->setDescription($request->getMethod() . ' ' . $request->getUri());

                $childSpan = $span->startChild($spanContext);

                $request->withHeader('sentry-trace', $childSpan->toTraceparent());

                $handlerPromiseCallback = static function ($responseOrException) use ($childSpan) {
                    $response = $responseOrException instanceof ResponseInterface ? $responseOrException : null;

                    /** @psalm-suppress UndefinedClass */
                    if ($responseOrException instanceof GuzzleRequestException) {
                        $response = $responseOrException->getResponse();
                    }

                    if (null !== $response) {
                        $spanData = $childSpan->getData();

                        $childSpan->setStatus(SpanStatus::createFromHttpStatusCode($responseOrException->getStatusCode()));

                        $spanData['status_code'] = $responseOrException->getStatusCode();
                        $spanData['response_body_size'] = $responseOrException->getBody()->getSize();

                        $childSpan->setData($spanData);
                    } else {
                        $childSpan->setStatus(SpanStatus::internalError());
                    }

                    $childSpan->finish();

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
