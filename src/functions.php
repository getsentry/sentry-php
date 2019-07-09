<?php

declare(strict_types=1);

namespace Sentry;

/**
 * Creates a new Client and Hub which will be set as current.
 *
 * @param array $options The client options
 */
function init(array $options = []): void
{
    $client = ClientBuilder::create($options)->getClient();

    SentrySdk::init()->bindClient($client);
}

/**
 * Captures a message event and sends it to Sentry.
 *
 * @param string   $message The message
 * @param Severity $level   The severity level of the message
 *
 * @return string|null
 *
 * @deprecated
 */
function captureMessage(string $message, ?Severity $level = null): ?string
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::captureMessage method instead.', __FUNCTION__), E_USER_DEPRECATED);

    return SentrySdk::captureMessage($message, $level);
}

/**
 * Captures an exception event and sends it to Sentry.
 *
 * @param \Throwable $exception The exception
 *
 * @return string|null
 *
 * @deprecated
 */
function captureException(\Throwable $exception): ?string
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::captureException method instead.', __FUNCTION__), E_USER_DEPRECATED);

    return SentrySdk::captureException($exception);
}

/**
 * Captures a new event using the provided data.
 *
 * @param array $payload The data of the event being captured
 *
 * @return string|null
 *
 * @deprecated
 */
function captureEvent(array $payload): ?string
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::captureEvent method instead.', __FUNCTION__), E_USER_DEPRECATED);

    return SentrySdk::captureEvent($payload);
}

/**
 * Logs the most recent error (obtained with {@link error_get_last}).
 *
 * @return string|null
 *
 * @deprecated
 */
function captureLastError(): ?string
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::captureLastError method instead.', __FUNCTION__), E_USER_DEPRECATED);

    return SentrySdk::captureLastError();
}

/**
 * Records a new breadcrumb which will be attached to future events. They
 * will be added to subsequent events to provide more context on user's
 * actions prior to an error or crash.
 *
 * @param Breadcrumb $breadcrumb The breadcrumb to record
 *
 * @deprecated
 */
function addBreadcrumb(Breadcrumb $breadcrumb): void
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::addBreadcrumb method instead.', __FUNCTION__), E_USER_DEPRECATED);

    SentrySdk::addBreadcrumb($breadcrumb);
}

/**
 * Calls the given callback passing to it the current scope so that any
 * operation can be run within its context.
 *
 * @param callable $callback The callback to be executed
 *
 * @deprecated
 */
function configureScope(callable $callback): void
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::configureScope method instead.', __FUNCTION__), E_USER_DEPRECATED);

    SentrySdk::configureScope($callback);
}

/**
 * Creates a new scope with and executes the given operation within. The scope
 * is automatically removed once the operation finishes or throws.
 *
 * @param callable $callback The callback to be executed
 *
 * @deprecated
 */
function withScope(callable $callback): void
{
    @trigger_error(sprintf('The function %s() is deprecated since version 2.2 and will be removed in 3.0. Use the SentrySdk::withScope method instead.', __FUNCTION__), E_USER_DEPRECATED);

    SentrySdk::withScope($callback);
}
