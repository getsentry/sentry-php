# Upgrade 4.x to 5.0

This guide provides details on how to upgrade from SDK version 4.x to 5.0.

## Breaking Changes

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

- **Removed functions:**
  - `metrics()` - The global metrics function has been removed

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

### EventType Changes

The `metrics` event type has been removed from `EventType::cases()` as metrics are no longer supported.
