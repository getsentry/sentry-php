<?php

declare(strict_types=1);

namespace Sentry;

use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;

/**
 * Creates a new Client and Hub which will be set as current.
 *
 * @param array<string, mixed> $options The client options
 */
function init(array $options = []): void
{
    $client = ClientBuilder::create($options)->getClient();

    SentrySdk::init()->bindClient($client);
}

/**
 * Captures a message event and sends it to Sentry.
 *
 * @param string        $message The message
 * @param Severity|null $level   The severity level of the message
 */
function captureMessage(string $message, ?Severity $level = null): ?EventId
{
    return SentrySdk::getCurrentHub()->captureMessage($message, $level);
}

/**
 * Captures an exception event and sends it to Sentry.
 *
 * @param \Throwable $exception The exception
 */
function captureException(\Throwable $exception): ?EventId
{
    return SentrySdk::getCurrentHub()->captureException($exception);
}

/**
 * Captures a new event using the provided data.
 *
 * @param Event          $event The event being captured
 * @param EventHint|null $hint  May contain additional information about the event
 */
function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
{
    return SentrySdk::getCurrentHub()->captureEvent($event, $hint);
}

/**
 * Logs the most recent error (obtained with {@link error_get_last}).
 */
function captureLastError(): ?EventId
{
    return SentrySdk::getCurrentHub()->captureLastError();
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
    SentrySdk::getCurrentHub()->addBreadcrumb($breadcrumb);
}

/**
 * Calls the given callback passing to it the current scope so that any
 * operation can be run within its context.
 *
 * @param callable $callback The callback to be executed
 */
function configureScope(callable $callback): void
{
    SentrySdk::getCurrentHub()->configureScope($callback);
}

/**
 * Creates a new scope with and executes the given operation within. The scope
 * is automatically removed once the operation finishes or throws.
 *
 * @param callable $callback The callback to be executed
 */
function withScope(callable $callback): void
{
    SentrySdk::getCurrentHub()->withScope($callback);
}

/**
 * Starts a new `Transaction` and returns it. This is the entry point to manual
 * tracing instrumentation.
 *
 * A tree structure can be built by adding child spans to the transaction, and
 * child spans to other spans. To start a new child span within the transaction
 * or any span, call the respective `startChild()` method.
 *
 * Every child span must be finished before the transaction is finished,
 * otherwise the unfinished spans are discarded.
 *
 * The transaction must be finished with a call to its `finish()` method, at
 * which point the transaction with all its finished child spans will be sent to
 * Sentry.
 *
 * @param TransactionContext   $context               Properties of the new transaction
 * @param array<string, mixed> $customSamplingContext Additional context that will be passed to the {@see \Sentry\Tracing\SamplingContext}
 */
function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
{
    /** @psalm-suppress TooManyArguments */
    return SentrySdk::getCurrentHub()->startTransaction($context, $customSamplingContext);
}
