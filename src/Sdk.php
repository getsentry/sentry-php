<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\State\Hub;

/**
 * Creates a new Client and Hub which will be set as current.
 *
 * @param array $arrayOptions The client options
 */
function init(array $arrayOptions = []): void
{
    $options = new Options($arrayOptions);
    $client = (new ClientBuilder($options))->getClient();

    $hub = new Hub($client);
    Hub::setCurrent($hub);

    foreach ($options->getDefaultIntegrations() as $integration) {
        $hub->addIntegration($integration);
    }

    foreach ($options->getIntegrations() as $integration) {
        $hub->addIntegration($integration);
    }
}

/**
 * Captures a message event and sends it to Sentry.
 *
 * @param string   $message The message
 * @param Severity $level   The severity level of the message
 *
 * @return string|null
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
 * @return string|null
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
 * @return string|null
 */
function captureEvent(array $payload): ?string
{
    return Hub::getCurrent()->captureEvent($payload);
}

/**
 * Logs the most recent error (obtained with {@link error_get_last}).
 *
 * @return string|null
 */
function captureLastError(): ?string
{
    return Hub::getCurrent()->captureLastError();
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
