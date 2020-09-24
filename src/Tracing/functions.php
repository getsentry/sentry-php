<?php

declare(strict_types=1);

namespace Sentry\Tracing;

use Sentry\SentrySdk;
use Psr\Http\Message\RequestInterface;


/**
 * This is a GuzzleHttp handler method to provide spans for outgoing requests.
 *
 * @return \Closure
 */
function addSentrySpans() {
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
