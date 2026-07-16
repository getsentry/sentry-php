<?php

declare(strict_types=1);

namespace Sentry;

use Psr\Log\LoggerInterface;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\OTLPIntegration;
use Sentry\Logs\Logs;
use Sentry\Metrics\TraceMetrics;
use Sentry\State\BreadcrumbRecorder;
use Sentry\State\EventCapturer;
use Sentry\State\Scope;
use Sentry\Tracing\PropagationContext;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Sentry\Transport\TransportInterface;

/**
 * Creates a new Client and initializes the SDK.
 *
 * @param array{
 *     attach_stacktrace?: bool,
 *     before_breadcrumb?: callable,
 *     before_send?: callable,
 *     before_send_check_in?: callable,
 *     before_send_log?: callable,
 *     before_send_transaction?: callable,
 *     capture_silenced_errors?: bool,
 *     context_lines?: int|null,
 *     default_integrations?: bool,
 *     dsn?: string|bool|Dsn|null,
 *     enable_logs?: bool,
 *     environment?: string|null,
 *     error_types?: int|null,
 *     http_client?: HttpClientInterface|null,
 *     http_compression?: bool,
 *     http_connect_timeout?: int|float,
 *     http_proxy?: string|null,
 *     http_proxy_authentication?: string|null,
 *     http_ssl_verify_peer?: bool,
 *     http_timeout?: int|float,
 *     http_enable_curl_share_handle?: bool,
 *     ignore_exceptions?: array<class-string>,
 *     ignore_transactions?: array<string>,
 *     in_app_exclude?: array<string>,
 *     in_app_include?: array<string>,
 *     integrations?: IntegrationInterface[]|callable(IntegrationInterface[]): IntegrationInterface[],
 *     logger?: LoggerInterface|null,
 *     log_flush_threshold?: int|null,
 *     metric_flush_threshold?: int|null,
 *     max_breadcrumbs?: int,
 *     max_request_body_size?: "never"|"small"|"medium"|"always",
 *     org_id?: int|null,
 *     prefixes?: array<string>,
 *     profiles_sample_rate?: int|float|null,
 *     profiles_sampler?: callable|null,
 *     release?: string|null,
 *     sample_rate?: float|int,
 *     send_attempts?: int,
 *     send_default_pii?: bool,
 *     server_name?: string,
 *     spotlight?: bool,
 *     strict_trace_continuation?: bool,
 *     tags?: array<string>,
 *     trace_propagation_targets?: array<string>|null,
 *     traces_sample_rate?: float|int|null,
 *     traces_sampler?: callable|null,
 *     transport?: TransportInterface|null,
 * } $options The client options
 */
function init(array $options = []): void
{
    $client = ClientBuilder::create($options)->getClient();

    SentrySdk::init($client);
}

function getGlobalScope(): Scope
{
    return SentrySdk::getGlobalScope();
}

function getIsolationScope(): Scope
{
    return SentrySdk::getIsolationScope();
}

function getClient(): ClientInterface
{
    return SentrySdk::getClient();
}

/**
 * Captures a message event and sends it to Sentry.
 *
 * @param string         $message The message
 * @param Severity|null  $level   The severity level of the message
 * @param EventHint|null $hint    Object that can contain additional information about the event
 */
function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
{
    return EventCapturer::captureMessage($message, $level, $hint);
}

/**
 * Captures an exception event and sends it to Sentry.
 *
 * @param \Throwable     $exception The exception
 * @param EventHint|null $hint      Object that can contain additional information about the event
 */
function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
{
    return EventCapturer::captureException($exception, $hint);
}

/**
 * Captures a new event using the provided data.
 *
 * @param Event          $event The event being captured
 * @param EventHint|null $hint  May contain additional information about the event
 */
function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
{
    return EventCapturer::captureEvent($event, $hint);
}

/**
 * Logs the most recent error (obtained with {@see error_get_last()}).
 *
 * @param EventHint|null $hint Object that can contain additional information about the event
 */
function captureLastError(?EventHint $hint = null): ?EventId
{
    return EventCapturer::captureLastError($hint);
}

/**
 * Captures a check-in and sends it to Sentry.
 *
 * @param string             $slug          Identifier of the Monitor
 * @param CheckInStatus      $status        The status of the check-in
 * @param int|float|null     $duration      The duration of the check-in
 * @param MonitorConfig|null $monitorConfig Configuration of the Monitor
 * @param string|null        $checkInId     A check-in ID from the previous check-in
 */
function captureCheckIn(string $slug, CheckInStatus $status, $duration = null, ?MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string
{
    return EventCapturer::captureCheckIn($slug, $status, $duration, $monitorConfig, $checkInId);
}

/**
 * Execute the given callable while wrapping it in a monitor check-in.
 *
 * @param string             $slug          Identifier of the Monitor
 * @param callable           $callback      The callable that is going to be monitored
 * @param MonitorConfig|null $monitorConfig Configuration of the Monitor
 *
 * @return mixed
 */
function withMonitor(string $slug, callable $callback, ?MonitorConfig $monitorConfig = null)
{
    $checkInId = captureCheckIn($slug, CheckInStatus::inProgress(), null, $monitorConfig);

    $status = CheckInStatus::ok();
    $duration = 0;

    try {
        $start = microtime(true);
        $result = $callback();
        $duration = microtime(true) - $start;

        return $result;
    } catch (\Throwable $e) {
        $status = CheckInStatus::error();

        throw $e;
    } finally {
        captureCheckIn($slug, $status, $duration, $monitorConfig, $checkInId);
    }
}

/**
 * Records a new breadcrumb which will be attached to future events. They
 * will be added to subsequent events to provide more context on user's
 * actions prior to an error or crash.
 *
 * @param Breadcrumb|string    $category  The category of the breadcrumb, can be a Breadcrumb instance as well (in which case the other parameters are ignored)
 * @param string|null          $message   Breadcrumb message
 * @param array<string, mixed> $metadata  Additional information about the breadcrumb
 * @param string               $level     The error level of the breadcrumb
 * @param string               $type      The type of the breadcrumb
 * @param float|null           $timestamp Optional timestamp of the breadcrumb
 */
function addBreadcrumb($category, ?string $message = null, array $metadata = [], string $level = Breadcrumb::LEVEL_INFO, string $type = Breadcrumb::TYPE_DEFAULT, ?float $timestamp = null): void
{
    $scope = SentrySdk::getIsolationScope();
    $breadcrumb = $category instanceof Breadcrumb
        ? $category
        : new Breadcrumb($level, $type, $category, $message, $metadata, $timestamp);

    BreadcrumbRecorder::record(SentrySdk::getClient($scope), $scope, $breadcrumb);
}

/**
 * Calls the given callback passing to it the current scope so that any
 * operation can be run within its context.
 *
 * @param callable $callback The callback to be executed
 */
function configureScope(callable $callback): void
{
    $callback(SentrySdk::getIsolationScope());
}

/**
 * Creates a new scope with and executes the given operation within. The scope
 * is automatically removed once the operation finishes or throws.
 *
 * @param callable $callback The callback to be executed
 *
 * @phpstan-template T
 *
 * @phpstan-param callable(Scope): T $callback
 *
 * @return mixed|void The callback's return value, upon successful execution
 *
 * @phpstan-return T
 *
 * @deprecated This function will be removed in a follow-up PR. Use {@see withIsolationScope()} instead.
 */
function withScope(callable $callback)
{
    return withIsolationScope($callback);
}

/**
 * Forks the current isolation scope for the duration of the callback.
 *
 * @param callable $callback The callback to be executed
 *
 * @phpstan-template T
 *
 * @phpstan-param callable(Scope): T $callback
 *
 * @return mixed|void The callback's return value, upon successful execution
 *
 * @phpstan-return T
 */
function withIsolationScope(callable $callback)
{
    $context = SentrySdk::getCurrentRuntimeContext();
    $previousScope = $context->getIsolationScope();
    $context->setIsolationScope(clone $previousScope);

    try {
        return $callback($context->getIsolationScope());
    } finally {
        $context->setIsolationScope($previousScope);
    }
}

function startContext(): void
{
    SentrySdk::startContext();
}

function endContext(?int $timeout = null): void
{
    SentrySdk::endContext($timeout);
}

/**
 * Executes the given callback within an isolated context.
 *
 * If a context is already active for the current execution key, it is reused.
 *
 * @param callable $callback The callback to execute
 * @param int|null $timeout  The maximum number of seconds to wait while flushing the client transport
 *
 * @phpstan-template T
 *
 * @phpstan-param callable(): T $callback
 *
 * @return mixed
 *
 * @phpstan-return T
 */
function withContext(callable $callback, ?int $timeout = null)
{
    return SentrySdk::withContext($callback, $timeout);
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
 * @param array<string, mixed> $customSamplingContext Additional context that will be passed to the {@see Tracing\SamplingContext}
 */
function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
{
    return Tracing\TransactionSampler::startTransaction(SentrySdk::getClient()->getOptions(), $context, $customSamplingContext);
}

/**
 * Execute the given callable while wrapping it in a span added as a child to the current transaction and active span.
 * If there is no transaction active this is a no-op and the scope passed to the trace callable will be unused.
 *
 * @template T
 *
 * @param callable(Scope): T $trace   The callable that is going to be traced
 * @param SpanContext        $context The context of the span to be created
 *
 * @return T
 */
function trace(callable $trace, SpanContext $context)
{
    return withIsolationScope(static function (Scope $scope) use ($context, $trace) {
        $parentSpan = $scope->getSpan();
        $span = null;

        // If there is a span set on the scope and it's sampled there is an active transaction.
        // If that is the case we create the child span and set it on the scope.
        // Otherwise we only execute the callable without creating a span.
        if ($parentSpan !== null && $parentSpan->getSampled()) {
            $span = $parentSpan->startChild($context);

            $scope->setSpan($span);
        }

        try {
            return $trace($scope);
        } finally {
            if ($span !== null) {
                $span->finish();

                $scope->setSpan($parentSpan);
            }
        }
    });
}

/**
 * Returns the OTLP traces endpoint configured for the current client.
 */
function getOtlpTracesEndpointUrl(): ?string
{
    $client = SentrySdk::getClient();

    $integration = $client->getIntegration(OTLPIntegration::class);
    if ($integration instanceof OTLPIntegration && $integration->getCollectorUrl() !== null) {
        return $integration->getCollectorUrl();
    }

    $dsn = $client->getOptions()->getDsn();
    if ($dsn === null) {
        return null;
    }

    return $dsn->getOtlpTracesEndpointUrl();
}

/**
 * Creates the current Sentry traceparent string, to be used as a HTTP header value
 * or HTML meta tag value.
 * This function is context aware, as in it either returns the traceparent based
 * on the current span, or the scope's propagation context.
 */
function getTraceparent(): string
{
    $client = SentrySdk::getClient();
    $options = $client->getOptions();
    $scope = SentrySdk::getIsolationScope();

    if ($options->isTracingEnabled()) {
        $span = $scope->getSpan();
        if ($span !== null) {
            return $span->toTraceparent();
        }
    }

    if ($scope->hasExternalPropagationContext()) {
        return '';
    }

    return $scope->getPropagationContext()->toTraceparent();
}

/**
 * Creates the baggage content string, to be used as a HTTP header value
 * or HTML meta tag value.
 * This function is context aware, as in it either returns the baggage based
 * on the current span or the scope's propagation context.
 */
function getBaggage(): string
{
    $client = SentrySdk::getClient();
    $options = $client->getOptions();
    $scope = SentrySdk::getIsolationScope();

    if ($options->isTracingEnabled()) {
        $span = $scope->getSpan();
        if ($span !== null) {
            return $span->toBaggage();
        }
    }

    if ($scope->hasExternalPropagationContext()) {
        return '';
    }

    return $scope->getPropagationContext()->toBaggage();
}

/**
 * Continue a trace based on HTTP header values.
 * If the SDK is configured with enabled tracing,
 * this function returns a populated TransactionContext.
 * In any other cases, it populates the propagation context on the scope.
 */
function continueTrace(string $sentryTrace, string $baggage): TransactionContext
{
    // With the new `strict_trace_continuation`, it's possible that we start two new
    // traces if we parse the TransactionContext and PropagationContext from the same
    // headers. To make sure the trace is the same, we will create one transaction
    // context from headers and copy relevant information over.
    $transactionContext = TransactionContext::fromHeaders($sentryTrace, $baggage);
    $propagationContext = PropagationContext::fromDefaults();
    $metadata = $transactionContext->getMetadata();

    $traceId = $transactionContext->getTraceId() ?? $propagationContext->getTraceId();
    $transactionContext->setTraceId($traceId);
    $propagationContext->setTraceId($traceId);

    $propagationContext->setParentSpanId($transactionContext->getParentSpanId());
    $propagationContext->setSampleRand($metadata->getSampleRand());

    $dynamicSamplingContext = $metadata->getDynamicSamplingContext();
    if ($dynamicSamplingContext !== null) {
        $propagationContext->setDynamicSamplingContext($dynamicSamplingContext);
    }

    SentrySdk::getIsolationScope()->setPropagationContext($propagationContext);

    return $transactionContext;
}

/**
 * Get the Sentry Logs client.
 */
function logger(): Logs
{
    return Logs::getInstance();
}

function metrics(): TraceMetrics
{
    return TraceMetrics::getInstance();
}

function traceMetrics(): TraceMetrics
{
    return TraceMetrics::getInstance();
}

/**
 * Adds a feature flag evaluation to the current scope.
 * When invoked repeatedly for the same name, the most recent value is used.
 */
function addFeatureFlag(string $name, bool $result): void
{
    SentrySdk::getIsolationScope()->addFeatureFlag($name, $result);
}

/**
 * Flushes all buffered telemetry data.
 *
 * This is a convenience facade that forwards the flush operation to all
 * internally managed components.
 *
 * Calling this method is equivalent to invoking `flush()` on each component
 * individually. It does not change flushing behavior, improve performance,
 * or reduce the number of network requests.
 */
function flush(): void
{
    SentrySdk::flush();
}
