<?php

namespace Sentry;

use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\State\Hub;

/**
 * Creates a new Client and Hub which will be set as current.
 *
 * @param array $options
 */
function init(array $options = []): void
{
    Hub::setCurrent(new Hub(ClientBuilder::create($options)->getClient()));
}

/**
 * Captures a message event and sends it to Sentry.
 *
 * @param string   $message The message
 * @param Severity $level   The severity level of the message
 *
 * @return null|string
 */
function captureMessage(string $message, ?Severity $level = null): ?string
{
    return Hub::getCurrent()->captureMessage($message, $level);
}

/**
 * Captures an exception event and sends it to Sentry.
 *
 * @param \Throwable $exception The exception
 *
 * @return null|string
 */
function captureException(\Throwable $exception): ?string
{
    return Hub::getCurrent()->captureException($exception);
}

/**
 * Captures a new event using the provided data.
 *
 * @param array $payload The data of the event being captured
 *
 * @return null|string
 */
function captureEvent(array $payload): ?string
{
    return Hub::getCurrent()->captureEvent($payload);
}

/**
 * Logs the most recent error (obtained with {@link error_get_last}).
 *
 * @return null|string
 */
function captureLastError(): ?string
{
    $error = error_get_last();

    if (null === $error || !isset($error['message'][0])) {
        return null;
    }

    $exception = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);

    return captureException($exception);
}

/**
 * Records a new breadcrumb which will be attached to future events. They
 * will be added to subsequent events to provide more context on user's
 * actions prior to an error or crash.
 *
 * @param Breadcrumb $breadcrumb The breadcrumb to record
 */
function addBreadcrumb(Breadcrumb $breadcrumb): void
{
    Hub::getCurrent()->addBreadcrumb($breadcrumb);
}

/**
 * Calls the given callback passing to it the current scope so that any
 * operation can be run within its context.
 *
 * @param callable $callback The callback to be executed
 */
function configureScope(callable $callback): void
{
    Hub::getCurrent()->configureScope($callback);
}

/**
 * Creates a new scope with and executes the given operation within. The scope
 * is automatically removed once the operation finishes or throws.
 *
 * @param callable $callback The callback to be executed
 */
function withScope(callable $callback): void
{
    Hub::getCurrent()->withScope($callback);
}
