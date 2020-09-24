<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Psr\Http\Message\RequestInterface;
use Sentry\SentrySdk;

/**
 * This is a GuzzleHttp handler method to provide spans for outgoing requests.
 */
final class GuzzleHandler
{
    /**
     * Returns the actual handler.
     *
     * @return \Closure
     */
    public static function getHandler()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $transaction = SentrySdk::getCurrentHub()->getTransaction();
                $span = null;
                if ($transaction instanceof Transaction) {
                    $spanContext = new SpanContext();
                    $spanContext->setOp('http.guzzle');
                    $spanContext->setDescription($request->getMethod() . ' ' . $request->getUri());
                    $span = $transaction->startChild($spanContext);
                }
                $result = $handler($request, $options);
                if (null !== $span) {
                    $span->finish();
                }

                return $result;
            };
        };
    }
}
