# Upgrade 4.x to 5.0

This guide provides details on how to upgrade from SDK version 4.x to 5.0.

## Breaking Changes

### Deprecated Monolog Handler Removed

The deprecated `Sentry\Monolog\Handler` class has been removed.

Use `Sentry\Monolog\LogsHandler` to capture Monolog records as Sentry logs:

```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
]);

$logger->pushHandler(new \Sentry\Monolog\LogsHandler());
```

To continue sending Monolog records to Sentry issues instead, use `Sentry\Monolog\LogToSentryIssueHandler` for log messages or `Sentry\Monolog\ExceptionToSentryIssueHandler` for exceptions.

### Hub APIs Removed

The Hub API has been removed. This includes:

- **Removed classes and interfaces:**
  - `Sentry\State\Hub`
  - `Sentry\State\HubAdapter`
  - `Sentry\State\HubInterface`

- **Removed methods:**
  - `SentrySdk::getCurrentHub()`
  - `SentrySdk::setCurrentHub()`

The SDK now exposes runtime state directly through `SentrySdk`, global helpers, and `Scope`:

```php
// Before (4.x)
\Sentry\SentrySdk::getCurrentHub()->configureScope(static function (\Sentry\State\Scope $scope): void {
    $scope->setTag('feature', 'checkout');
});

// After (5.0)
\Sentry\configureScope(static function (\Sentry\State\Scope $scope): void {
    $scope->setTag('feature', 'checkout');
});
```

Use `SentrySdk::getClient()` for the active client, `SentrySdk::getGlobalScope()` for process-global data, and `SentrySdk::getIsolationScope()` for the current runtime isolation scope. Use the capture helpers such as `captureMessage()`, `captureException()`, and `captureEvent()` to send events.

Use `configureScope()` to mutate the current isolation scope, `withIsolationScope()` to run code with a temporary forked scope, and `startTransaction()` to start manual tracing instrumentation.

`SentrySdk::init()` no longer returns a Hub instance. It now returns `void`.

### Metrics API Removed

The entire Metrics API has been removed as it is no longer supported. This includes:

- **Removed classes:**
  - `Sentry\Metrics\Metrics`
  - `Sentry\Metrics\MetricsUnit`

- **Removed methods:**
  - `Event::createMetrics()`
  - `Event::getMetrics()` and `Event::setMetrics()`
  - `Event::getMetricsSummary()` and `Event::setMetricsSummary()`
  - `Span::getMetricsSummary()` and `Span::setMetricsSummary()`

- **Changed functions:**
  - `metrics()` - No longer returns the removed `Sentry\Metrics\Metrics` class, it now returns `Sentry\Metrics\TraceMetrics`

- **Removed functions:**
  - `traceMetrics()` and `trace_metrics()` - Use `metrics()` instead
    ```php
    // Before (4.x)
    \Sentry\traceMetrics()->count('checkout.failed', 1);

    // After (5.0)
    \Sentry\metrics()->count('checkout.failed', 1);
    ```

- **Removed options:**
  - `attach_metric_code_locations` - No longer available in Options
  - `before_send_metrics` callback - No longer available in Options

### Deprecated Options Removed

The following deprecated options have been removed from the `Options` class:

- **`enable_tracing`** - Use `traces_sample_rate` or `traces_sampler` instead
  ```php
  // Before (4.x)
  $options->setEnableTracing(true);
  
  // After (5.0)
  $options->setTracesSampleRate(1.0);
  ```

- **`spotlight_url`** - Use the `spotlight` option instead
  ```php
  // Before (4.x)
  $options->setSpotlightUrl('http://localhost:8969');
  
  // After (5.0)
  $options->enableSpotlight('http://localhost:8969');
  // or just enable with default URL
  $options->enableSpotlight(true);
  ```

- **`enable_logs`** and **`enable_metrics`** - Logs and metrics are always captured when their APIs or integrations are used, so both options and their `Options` getters and setters have been removed. To stop sending them, return `null` from the `before_send_log` or `before_send_metric` callback
  ```php
  // Before (4.x)
  \Sentry\init([
      'enable_logs' => true,
      'enable_metrics' => false,
  ]);

  // After (5.0)
  \Sentry\init([
      'before_send_metric' => static function (\Sentry\Metrics\Types\Metric $metric): ?\Sentry\Metrics\Types\Metric {
          return null;
      },
  ]);
  ```

### User Segment Removed

The `segment` property has been removed from the `UserDataBag` class:

```php
// Before (4.x)
$user = new UserDataBag(
    id: '123',
    email: 'user@example.com',
    segment: 'premium' // This parameter is removed
);

// After (5.0)
// Use custom tags or context instead
$user = new UserDataBag(
    id: '123',
    email: 'user@example.com'
);
$scope->setTag('user_segment', 'premium');
// or
$scope->setContext('user', ['segment' => 'premium']);
```

### Deprecated Methods Removed

The following deprecated methods have been removed:

- **`Span::toW3CTraceparent()`** - Use `Span::toTraceparent()` instead
  ```php
  // Before (4.x)
  $traceparent = $span->toW3CTraceparent();
  
  // After (5.0)
  $traceparent = $span->toTraceparent();
  ```

- **`SpanStatus::resourceExchausted()`** (typo) - Use `SpanStatus::resourceExhausted()` instead
  - Note: This was a typo fix where the misspelled method `resourceExchausted` was removed

- **`getW3CTraceparent()`** function - Use `getTraceparent()` instead
  ```php
  // Before (4.x)
  $traceparent = \Sentry\getW3CTraceparent();
  
  // After (5.0)
  $traceparent = \Sentry\getTraceparent();
  ```

- **`withScope()`** function - Use `withIsolationScope()` instead
  ```php
  // Before (4.x)
  \Sentry\withScope(static function (\Sentry\State\Scope $scope): void {
      $scope->setTag('feature', 'checkout');
  });

  // After (5.0)
  \Sentry\withIsolationScope(static function (\Sentry\State\IsolationScope $scope): void {
      $scope->setTag('feature', 'checkout');
  });
  ```

- **`Unit::none()`** and **`Unit::custom()`** - Use one of the supported units, or omit the unit

- **`HttpCookieCollector::collectPsr7Request()`** and **`HttpCookieCollector::collectPsr7Response()`** - Use `HttpSpanDataCollector::collectCookieHeaders()` with the `Cookie` or `Set-Cookie` headers instead

- **`HttpCookieCollector::collectGroupedPairs()`** - Use `HttpSpanDataCollector::collectCookiePairs()` instead

- **`HttpCookieParser::parseCookieHeaders()`** and **`HttpCookieParser::parseSetCookieHeaders()`** - Use `HttpCookieParser::splitCookieHeaders()` and `HttpCookieParser::splitSetCookieHeaders()` instead

### EventType Changes

The `metrics` event type has been removed from `EventType::cases()` as metrics are no longer supported.

### Request Body Size Option Value Changed

The `max_request_body_size` option value `'none'` has been renamed to `'never'` for consistency:

```php
// Before (4.x)
$options->setMaxRequestBodySize('none');

// After (5.0)
$options->setMaxRequestBodySize('never');
```

The allowed values for this option are now: `'never'`, `'small'`, `'medium'`, `'always'`.

### 404 Transactions Are No Longer Sent By Default

The `trace_ignore_status_codes` option now defaults to `[404]`. Transactions whose `http.response.status_code` data is `404` are discarded instead of being sent to Sentry.

To keep tracing all requests regardless of their status code, set the option to an empty list:

```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
    'trace_ignore_status_codes' => [],
]);
```
