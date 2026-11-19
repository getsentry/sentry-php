# CHANGELOG

## 5.0.0-beta2

The Sentry SDK team is happy to announce the second beta release of Sentry PHP SDK v5.0.0.

This is a pre-release of the next major version. The API can still change before the stable release, so we do not recommend it for production yet. To try it out, install the beta explicitly:

```bash
composer require "sentry/sentry:^5.0@beta"
```

Please refer to the [UPGRADE-5.0.md](UPGRADE-5.0.md) guide for a complete list of breaking changes and how to migrate.

### Features

- Add `Scope::setUserResolver()` to resolve the user lazily each time it is read, instead of setting it upfront. User data set with `Scope::setUser()` takes precedence over the data the resolver returns. [(#2396)](https://github.com/getsentry/sentry-php/pull/2396)

  ```php
  \Sentry\configureScope(static function (\Sentry\State\IsolationScope $scope): void {
      $scope->setUserResolver(static function (): ?\Sentry\UserDataBag {
          // Return null as long as the user is not known yet
          return \Sentry\UserDataBag::createFromUserIdentifier('1234');
      });
  });
  ```

### Bug Fixes

- Only send the `span_id` of a metric when a span is active or the trace was continued from an incoming request. `Metric::getSpanId()` returns `null` otherwise, instead of a span ID that belongs to no span. [(#2403)](https://github.com/getsentry/sentry-php/pull/2403)
- Record a client report for events that are dropped because the HTTP client fails to send them. [(#2401)](https://github.com/getsentry/sentry-php/pull/2401)

## 5.0.0-beta1

The Sentry SDK team is happy to announce the first beta release of Sentry PHP SDK v5.0.0.

This is a pre-release of the next major version. The API can still change before the stable release, so we do not recommend it for production yet. To try it out, install the beta explicitly:

```bash
composer require "sentry/sentry:^5.0@beta"
```

### Breaking Changes

Please refer to the [UPGRADE-5.0.md](UPGRADE-5.0.md) guide for a complete list of breaking changes and how to migrate.

<details>
<summary>Upgrading with an AI coding agent? Use this prompt.</summary>

```text
Upgrade this project from version 4 to version 5 of the Sentry PHP SDK (sentry/sentry).

Before you change anything, check composer.json. If the project requires sentry/sentry-laravel or
sentry/sentry-symfony, stop and tell me: those packages decide which version of sentry/sentry is
installed, so they have to be upgraded instead.

Otherwise:

1. Run `composer require "sentry/sentry:^5.0@beta"`.
2. Read vendor/sentry/sentry/UPGRADE-5.0.md completely. It is the source of truth for every breaking
   change. Version 5 is newer than what you know about this SDK, so do not rely on your memory of its
   API. When the guide does not cover something, read the code in vendor/sentry/sentry/src instead of
   guessing.
3. Search the whole project for usages of the SDK, not only the place where \Sentry\init() is called:
   everything from the Sentry\ namespace, the \Sentry\...() helper functions, the options passed to
   init() (they may live in config files or environment-specific overrides), the Monolog handler
   setup, custom integrations, event processors, and classes that implement or extend SDK types.
4. Apply the changes from the guide to every usage you found.
5. Run the static analysis and the tests of the project, if it has them, and fix what the upgrade
   broke.

Some changes do not cause an error, so nothing will point you to them. Check each of these explicitly:

- Removed options are ignored silently. Compare every option passed to init() with the guide.
- The send_default_pii option is gone, and the defaults of the data_collection option collect more
  data than before, whichever value send_default_pii had. What gets collected is a privacy decision
  that is mine to make: apply the restrictive configuration from the guide, which is the closest to
  the old behavior, and list what I could enable on top of it. This only applies if the project
  does not set the data_collection option yet. If it already does, send_default_pii was already
  ignored and the existing data_collection configuration keeps applying, so only remove
  send_default_pii.
- The client is never null anymore. Remove the null checks, and tell me where one of them was used
  to detect that the SDK is not initialized.
- Sentry\Metrics\Metrics and \Sentry\metrics() still exist, but they are a different API now. Code
  that calls them still has to be migrated.
- Scope data that is set before startContext() or withContext() is no longer inherited by the
  context. If this project runs long-lived workers (FrankenPHP, RoadRunner, Swoole, queue workers),
  move the data that should apply to every request or job to the global scope.
- Event processors no longer run for check-ins, logs and metrics. If one of them filters or scrubs
  that data, move the logic to before_send_check_in, before_send_log or before_send_metric.

When you are done, give me a summary of what you changed and a separate list of what I have to
decide or check myself. Include the changes that affect what shows up in Sentry: transactions with
a 404 status are no longer sent, error events can get a different transaction name, and logs carry
a span_id instead of the sentry.trace.parent_span_id attribute.
```

</details>

- The Hub API has been removed, including `Hub`, `HubInterface`, `HubAdapter`, `SentrySdk::getCurrentHub()` and `SentrySdk::setCurrentHub()`. Use the global helper functions, `SentrySdk::getClient()` and the new scopes instead. [(#2157)](https://github.com/getsentry/sentry-php/pull/2157)

  ```php
  // Before
  \Sentry\SentrySdk::getCurrentHub()->configureScope(static function (\Sentry\State\Scope $scope): void {
      $scope->setTag('feature', 'checkout');
  });

  // After
  \Sentry\configureScope(static function (\Sentry\State\IsolationScope $scope): void {
      $scope->setTag('feature', 'checkout');
  });
  ```

- `Sentry\State\Scope` is now abstract and split into a `GlobalScope` and an `IsolationScope`. `withScope()` has been replaced by `withIsolationScope()`, and `Scope::addGlobalEventProcessor()` by `SentrySdk::getGlobalScope()->addEventProcessor()`. Event processors only run for error events and transactions. [(#2157)](https://github.com/getsentry/sentry-php/pull/2157), [(#2245)](https://github.com/getsentry/sentry-php/pull/2245)

- A runtime context started with `startContext()` or `withContext()` starts with an empty isolation scope and no longer inherits the data set before it was started. Set data that applies to every context on the global scope. [(#2157)](https://github.com/getsentry/sentry-php/pull/2157)

- The `send_default_pii` option has been removed. The `data_collection` option now always decides which data the SDK collects, and its defaults collect more data than `send_default_pii` did. The `context_lines` option moved to `data_collection.frame_context_lines`. [(#2356)](https://github.com/getsentry/sentry-php/pull/2356)

- The `trace_ignore_status_codes` option now defaults to `[404]`, so transactions of requests that end in a `404` response are no longer sent. [(#2338)](https://github.com/getsentry/sentry-php/pull/2338)

- The old Metrics API has been removed. `Sentry\Metrics\TraceMetrics` has been renamed to `Sentry\Metrics\Metrics`, and `\Sentry\traceMetrics()` has been removed in favor of `\Sentry\metrics()`. [(#1877)](https://github.com/getsentry/sentry-php/pull/1877), [(#2357)](https://github.com/getsentry/sentry-php/pull/2357)

- The `enable_logs` and `enable_metrics` options have been removed. Logs and metrics are always captured when their APIs or integrations are used. [(#2355)](https://github.com/getsentry/sentry-php/pull/2355)

- All deprecated options and methods have been removed, such as `enable_tracing`, `spotlight_url`, `strict_trace_propagation`, the user `segment`, `getW3CTraceparent()` and `SpanStatus::resourceExchausted()`. [(#1877)](https://github.com/getsentry/sentry-php/pull/1877), [(#2355)](https://github.com/getsentry/sentry-php/pull/2355)

- The deprecated `Sentry\Monolog\Handler` has been removed, and the remaining Monolog handlers no longer take a `HubInterface` as their first constructor argument. [(#2136)](https://github.com/getsentry/sentry-php/pull/2136), [(#2157)](https://github.com/getsentry/sentry-php/pull/2157)

- The SDK no longer truncates string values, the `max_value_length` option has been removed. [(#1903)](https://github.com/getsentry/sentry-php/pull/1903)

- The `max_request_body_size` option no longer accepts `'none'`, use `'never'` instead. [(#1942)](https://github.com/getsentry/sentry-php/pull/1942)

- The `symfony/options-resolver` and `jean85/pretty-package-versions` dependencies have been removed, along with the packages that were only installed through them. The `ModulesIntegration` requires Composer 2. [(#1900)](https://github.com/getsentry/sentry-php/pull/1900), [(#1914)](https://github.com/getsentry/sentry-php/pull/1914), [(#1922)](https://github.com/getsentry/sentry-php/pull/1922)

### Features

- Add a global scope and an isolation scope. Data on the global scope is applied to every event of the process and survives across requests in long-running workers, while the isolation scope holds the data of the current runtime context, for example a single request. [(#2157)](https://github.com/getsentry/sentry-php/pull/2157)

  ```php
  // Applied to every event of this worker process
  \Sentry\getGlobalScope()->setTag('worker', 'queue-1');

  // Applied to the events of the current request or job only
  \Sentry\getIsolationScope()->setUser(['id' => '123']);
  ```

- Add `withIsolationScope()`, which forks the current isolation scope for the duration of a callback, and `SentrySdk::pushIsolationScope()` and `SentrySdk::popIsolationScope()` for code that cannot be wrapped in a callback. [(#2157)](https://github.com/getsentry/sentry-php/pull/2157), [(#2315)](https://github.com/getsentry/sentry-php/pull/2315)

- Add `\Sentry\getClient()` and `SentrySdk::getClient()`, which always return a client. Before the SDK is initialized they return a `NoOpClient`, so the client no longer has to be checked for `null`. [(#1957)](https://github.com/getsentry/sentry-php/pull/1957)

### Bug Fixes

- Use the name of the active transaction as the `transaction` of error events, and fall back to the path of `REQUEST_URI` when `PATH_INFO` is not set. [(#2339)](https://github.com/getsentry/sentry-php/pull/2339)
- Set the status of a span to `internal_error` when the callback passed to `\Sentry\trace()` throws. [(#2371)](https://github.com/getsentry/sentry-php/pull/2371)
- Report the duration of a failed run in `\Sentry\withMonitor()` instead of `0`. [(#2372)](https://github.com/getsentry/sentry-php/pull/2372)
- Replace PSR-3 placeholders with the values of the context in the `Sentry\Monolog\LogsHandler`, and send the template and its parameters as attributes. [(#2377)](https://github.com/getsentry/sentry-php/pull/2377)
- Send the active span of a log as its `span_id` instead of the `sentry.trace.parent_span_id` attribute. [(#2375)](https://github.com/getsentry/sentry-php/pull/2375)
- Use a Spotlight URL that has a path as it is, instead of always appending `/stream`. [(#2376)](https://github.com/getsentry/sentry-php/pull/2376)
- Draw the random numbers for sampling decisions from the operating system, so that forked processes no longer continue the same sequence. [(#2369)](https://github.com/getsentry/sentry-php/pull/2369)
- Send the pending client reports on `\Sentry\flush()` and when a runtime context ends, with the client of that context. [(#2368)](https://github.com/getsentry/sentry-php/pull/2368)
- Record a client report for transactions that are dropped by `trace_ignore_status_codes`. [(#2367)](https://github.com/getsentry/sentry-php/pull/2367)
- Fix the argument names of closures in stack frames. [(#2370)](https://github.com/getsentry/sentry-php/pull/2370)
- Omit the `unit` of a metric and of a crontab monitor schedule when it is not set. [(#2373)](https://github.com/getsentry/sentry-php/pull/2373), [(#2374)](https://github.com/getsentry/sentry-php/pull/2374)

## 4.38.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.38.0.

### Features

- Add `Scope::setUserResolver()` to resolve the user lazily each time it is read, instead of setting it upfront. User data set with `Scope::setUser()` takes precedence over the data the resolver returns. [(#2396)](https://github.com/getsentry/sentry-php/pull/2396)

  ```php
  \Sentry\configureScope(static function (\Sentry\State\Scope $scope): void {
      $scope->setUserResolver(static function (): ?\Sentry\UserDataBag {
          // Return null as long as the user is not known yet
          return \Sentry\UserDataBag::createFromUserIdentifier('1234');
      });
  });
  ```

## 4.37.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.37.1.

### Bug Fixes

- Write the HTTP status code into the `http.response.status_code` data of a span in `Span::setHttpStatus()`, so that the `trace_ignore_status_codes` option also applies to transactions whose status is set this way. [(#2384)](https://github.com/getsentry/sentry-php/pull/2384)
- Fix a `TypeError` when the response of the HTTP client contains a header with a numeric name. [(#2366)](https://github.com/getsentry/sentry-php/pull/2366)

## 4.37.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.37.0.

### Features

- Add stack traces to fatal errors on PHP 8.5 and later, which provides their backtrace unless the `fatal_error_backtraces` INI setting is disabled. [(#2346)](https://github.com/getsentry/sentry-php/pull/2346)
- Record more dropped telemetry in client reports, such as spans removed by event processors and `before_send_transaction`, logs and metrics dropped by their buffer limits, invalid profiles and profile chunks, and events that fail to serialize. [(#2319)](https://github.com/getsentry/sentry-php/pull/2319), [(#2322)](https://github.com/getsentry/sentry-php/pull/2322), [(#2324)](https://github.com/getsentry/sentry-php/pull/2324), [(#2325)](https://github.com/getsentry/sentry-php/pull/2325), [(#2326)](https://github.com/getsentry/sentry-php/pull/2326), [(#2327)](https://github.com/getsentry/sentry-php/pull/2327), [(#2328)](https://github.com/getsentry/sentry-php/pull/2328), [(#2329)](https://github.com/getsentry/sentry-php/pull/2329), [(#2334)](https://github.com/getsentry/sentry-php/pull/2334)

### Misc

- Only trim the breadcrumbs of a scope when they exceed `max_breadcrumbs`, instead of on every added breadcrumb. [(#2321)](https://github.com/getsentry/sentry-php/pull/2321)

## 4.36.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.36.0.

### Features

- Add continuous profiling, which samples the process for as long as the profiler runs and sends the samples in chunks. Enable it with `profile_session_sample_rate` and control it with `\Sentry\profiler()`, including `pause()` and `resume()` between requests in long-running workers, or set `profile_lifecycle` to `trace` to run it while a sampled transaction is active. Requires the `excimer` extension. [(#2303)](https://github.com/getsentry/sentry-php/pull/2303), [(#2310)](https://github.com/getsentry/sentry-php/pull/2310)

  ```php
  // Regular profiling, e.g. a request or a CLI command
  \Sentry\init([
      'dsn' => '__YOUR_DSN__',
      'profile_session_sample_rate' => 1.0,
  ]);

  \Sentry\profiler()->start();

  // ...

  \Sentry\profiler()->stop();
  ```

  ```php
  // Long-running runtimes like FrankenPHP, RoadRunner or Swoole, pause the profiler between requests
  \Sentry\profiler()->start();

  $handler = static function (): void {
      \Sentry\profiler()->resume();

      // Handle the request

      \Sentry\profiler()->pause();
  };

  while (frankenphp_handle_request($handler)) {
  }
  ```

- Add support for PHP 8.6. [(#2307)](https://github.com/getsentry/sentry-php/pull/2307)
- Record more dropped telemetry in client reports, such as ignored events, unsampled transactions, overflowing spans and failed sends. [(#2299)](https://github.com/getsentry/sentry-php/pull/2299), [(#2306)](https://github.com/getsentry/sentry-php/pull/2306), [(#2308)](https://github.com/getsentry/sentry-php/pull/2308), [(#2309)](https://github.com/getsentry/sentry-php/pull/2309), [(#2316)](https://github.com/getsentry/sentry-php/pull/2316), [(#2317)](https://github.com/getsentry/sentry-php/pull/2317), [(#2318)](https://github.com/getsentry/sentry-php/pull/2318)

## 4.35.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.35.0.

### Features

- Add the `trace_ignore_status_codes` option to discard transactions for incoming requests whose response status code matches. Each entry is either a status code or an inclusive `[min, max]` range. [(#2263)](https://github.com/getsentry/sentry-php/pull/2263)

  ```php
  \Sentry\init([
      'dsn' => '__YOUR_DSN__',
      'traces_sample_rate' => 1.0,
      'trace_ignore_status_codes' => [404, [500, 599]],
  ]);
  ```

- Add the `max_breadth` option to limit how many items of an array or object are serialized in frame arguments and query bindings. Items past the limit are replaced by a `{truncated}` entry. [(#2283)](https://github.com/getsentry/sentry-php/pull/2283)
- Add the `profiler_factory` option to use a custom profiler for transactions sampled for profiling, for example to keep samples from other requests out of a profile in async servers. [(#2253)](https://github.com/getsentry/sentry-php/pull/2253)

  ```php
  \Sentry\init([
      'dsn' => '__YOUR_DSN__',
      'traces_sample_rate' => 1.0,
      'profiles_sample_rate' => 1.0,
      'profiler_factory' => static function (\Sentry\Options $options): \Sentry\Profiling\ProfilerInterface {
          return new MyProfiler($options);
      },
  ]);
  ```

- Add the `send_client_reports` option to record telemetry discarded by `before_send` callbacks, event processors, `sample_rate` and active rate limits. It defaults to `false`. [(#2287)](https://github.com/getsentry/sentry-php/pull/2287), [(#2294)](https://github.com/getsentry/sentry-php/pull/2294), [(#2296)](https://github.com/getsentry/sentry-php/pull/2296), [(#2297)](https://github.com/getsentry/sentry-php/pull/2297), [(#2298)](https://github.com/getsentry/sentry-php/pull/2298), [(#2300)](https://github.com/getsentry/sentry-php/pull/2300), [(#2301)](https://github.com/getsentry/sentry-php/pull/2301), [(#2302)](https://github.com/getsentry/sentry-php/pull/2302)

### Bug Fixes

- Filter URL signatures, such as Laravel signed routes and presigned S3 URLs, when collecting query strings, headers and cookies. [(#2293)](https://github.com/getsentry/sentry-php/pull/2293)
- Filter query parameters in `Referer` headers. [(#2256)](https://github.com/getsentry/sentry-php/pull/2256)
- Run processors in `ExceptionToSentryIssueHandler`, so the `monolog.message` extra contains the interpolated message. [(#2290)](https://github.com/getsentry/sentry-php/pull/2290)
- Propagate the trace of the hub passed to `GuzzleTracingMiddleware` instead of the current hub. `getTraceparent()` and `getBaggage()` now accept an optional hub. [(#2289)](https://github.com/getsentry/sentry-php/pull/2289)
- Don't nest concurrent Guzzle requests under each other, and don't leave a finished request span as the current span. [(#2255)](https://github.com/getsentry/sentry-php/pull/2255)
- Don't treat a list of two allowed option values as a callable during option validation. [(#2258)](https://github.com/getsentry/sentry-php/pull/2258)
- Stop the profiler only once when a transaction is finished more than once, so its samples are not lost. [(#2253)](https://github.com/getsentry/sentry-php/pull/2253)

### Misc

- Only attach stacktraces from `attach_stacktrace` to error events, and skip preparing errors discarded by `sample_rate`. [(#2284)](https://github.com/getsentry/sentry-php/pull/2284)

## 4.34.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.34.0.

### Features

- Add the `$includeChannel` argument to the Monolog `LogsHandler` to attach the Monolog channel as the `channel` attribute on Sentry logs. [(#2029)](https://github.com/getsentry/sentry-php/pull/2029)

  ```php
  $logger->pushHandler(new \Sentry\Monolog\LogsHandler(LogLevel::info(), true, true));
  ```

### Bug Fixes

- Always filter "remember me" cookies, such as Laravel's `remember_web_*` and Symfony's `REMEMBERME`, when collecting HTTP data. [(#2250)](https://github.com/getsentry/sentry-php/pull/2250)
- Collect cleared cookies with an empty value instead of `[Filtered]`. [(#2250)](https://github.com/getsentry/sentry-php/pull/2250)
- Replace invalid UTF-8 in collected HTTP bodies instead of filtering the whole body, and stop escaping non-ASCII characters. [(#2250)](https://github.com/getsentry/sentry-php/pull/2250)

### Misc

- Cache source code excerpts per event in `FrameContextifierIntegration` so duplicate frames don't read the same file again. [(#2163)](https://github.com/getsentry/sentry-php/pull/2163)

## 4.33.2

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.33.2.

### Bug Fixes

- Collect cookies as string arrays in the form of `key=value` instead of maps and bodies as JSON strings. [(#2246)](https://github.com/getsentry/sentry-php/pull/2246)

### Misc

- Only read the request body in `RequestIntegration` for error events, as it is not used for transactions. [(#2247)](https://github.com/getsentry/sentry-php/pull/2247)
- Add `HttpSpanDataCollector` to share HTTP span attribute names and formats with the framework integrations. [(#2246)](https://github.com/getsentry/sentry-php/pull/2246)

## 4.33.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.33.1.

### Bug Fixes

- Join multiple values of the same header into a single comma-separated string in Guzzle span data. [(#2241)](https://github.com/getsentry/sentry-php/pull/2241)

## 4.33.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.33.0.

### Features

- Add the `data_collection` option to control which potentially sensitive data the SDK collects. Setting it to any value other than `null` opts into the new behavior and ignores `send_default_pii`. [(#2227)](https://github.com/getsentry/sentry-php/pull/2227)

  ```php
  \Sentry\init([
      'dsn' => '__YOUR_DSN__',
      'data_collection' => [
          'cookies' => ['mode' => 'allowList', 'terms' => ['locale']],
          'http_bodies' => ['incomingRequest'],
          'stack_frame_variables' => false,
      ],
  ]);
  ```

- Allow registering a fallback serializer for any object by using `object` as the key in `class_serializers`. [(#2232)](https://github.com/getsentry/sentry-php/pull/2232)
- Apply rate limits to attachments. [(#2233)](https://github.com/getsentry/sentry-php/pull/2233)

### Bug Fixes

- Respect rate limits on `429` responses that include a response body. [(#2225)](https://github.com/getsentry/sentry-php/pull/2225)
- Parse every limit in the `X-Sentry-Rate-Limits` header instead of falling back to the default duration for all but the first one. [(#2226)](https://github.com/getsentry/sentry-php/pull/2226)
- Keep the longest rate limit when multiple limits apply to the same category, apply the default rate limit to `429` responses without rate limit headers, and handle decimal durations. [(#2234)](https://github.com/getsentry/sentry-php/pull/2234)

### Misc

- Exclude development files from the distributed package. [(#2230)](https://github.com/getsentry/sentry-php/pull/2230)

## 4.32.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.32.0.

### Features

- Add attachment support (backport from v5). [(#2217)](https://github.com/getsentry/sentry-php/pull/2217)
- Support concurrent runtime contexts with `RuntimeContextStorageInterface` and `SentrySdk::setRuntimeContextStorage()`, allowing integrations to isolate telemetry from overlapping executions. [(#2190)](https://github.com/getsentry/sentry-php/pull/2190)
- Allow passing a `HubInterface` to `startContext()` when starting an isolated runtime context. [(#2191)](https://github.com/getsentry/sentry-php/pull/2191)
- Handle exceptions thrown by user-provided callbacks without crashing the application. [(#2199)](https://github.com/getsentry/sentry-php/pull/2199)

### Bug Fixes

- Avoid PHP 8.5 warnings when serializing `INF`, `NAN`, and floats outside the integer range. [(#2192)](https://github.com/getsentry/sentry-php/pull/2192)

## 4.31.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.31.0.

### Features

- Always capture logs and metrics when their APIs or integrations are used. The deprecated `enable_logs` and `enable_metrics` options no longer have any effect. To disable sending, return `null` from `before_send_log` or `before_send_metric`. [(#2187)](https://github.com/getsentry/sentry-php/pull/2187)

### Misc

- Accept `null` metric attribute values in public type declarations. [(#2185)](https://github.com/getsentry/sentry-php/pull/2185)
- Add the missing `enable_metrics` option to the `init()` PHPDoc. [(#2184)](https://github.com/getsentry/sentry-php/pull/2184)
- Add the Yii3 community integration to the README. [(#2180)](https://github.com/getsentry/sentry-php/pull/2180)

## 4.30.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.30.0.

### Features

- Add support for `guzzlehttp/psr7` 3.x and `guzzlehttp/promises` 3.x. [(#2164)](https://github.com/getsentry/sentry-php/pull/2164)

### Bug Fixes

- Strip breadcrumb metadata when capturing out-of-memory errors to prevent events with large breadcrumbs from being silently dropped. [(#2150)](https://github.com/getsentry/sentry-php/pull/2150)

### Misc

- Use the SDK's internal options resolver and fall back to safe defaults when option values are invalid. [(#2151)](https://github.com/getsentry/sentry-php/pull/2151)
- Improve `RingBuffer` performance by using native PHP arrays. [(#2159)](https://github.com/getsentry/sentry-php/pull/2159)
- Improve stack trace construction performance. [(#2160)](https://github.com/getsentry/sentry-php/pull/2160)
- Correct `BreadcrumbHandler` PHPDoc parameter types for static analysis tools. [(#2152)](https://github.com/getsentry/sentry-php/pull/2152)

## 4.29.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.29.0.

### Features

- Scrub `Proxy-Authorization` request headers by default. [(#2126)](https://github.com/getsentry/sentry-php/pull/2126)
- Send `gen_ai` spans using the span v2 protocol. [(#2141)](https://github.com/getsentry/sentry-php/pull/2141)

### Bug Fixes

- Reset fatal error handler state when starting a new runtime context. [(#2128)](https://github.com/getsentry/sentry-php/pull/2128)

## 4.28.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.28.0.

### Features

- Add `SentryPropagator` to inject and extract `sentry-trace` headers in OpenTelemetry contexts. [(#2105)](https://github.com/getsentry/sentry-php/pull/2105)

### Bug Fixes

- Keep feature flag values serialized as a JSON list when a flag is updated multiple times. [(#2104)](https://github.com/getsentry/sentry-php/pull/2104)

## 4.27.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.27.0.

### Features

- Add `profiles_sampler` option. [(#2082)](https://github.com/getsentry/sentry-php/pull/2082)

### Bug Fixes

- Preserve manually configured user attributes on logs and metrics when `send_default_pii` is disabled. [(#2083)](https://github.com/getsentry/sentry-php/pull/2083)

### Misc

- Add Mago static analysis to CI. [(#2020)](https://github.com/getsentry/sentry-php/pull/2020)

## 4.26.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.26.0.

### Features

- Add `AgentClient` and `AgentClientBuilder` to hand off envelopes to a local Sentry agent. [(#2062)](https://github.com/getsentry/sentry-php/pull/2062)
- Add fallback HTTP delivery for `AgentClient` when the local Sentry agent is unavailable. [(#2072)](https://github.com/getsentry/sentry-php/pull/2072)
- Add `LogToSentryIssueHandler` Monolog handler to capture log messages as Sentry issues. [(#2075)](https://github.com/getsentry/sentry-php/pull/2075)

### Bug Fixes

- Respect `send_default_pii` before attaching user attributes to Sentry logs. [(#2076)](https://github.com/getsentry/sentry-php/pull/2076)
- Ignore invalid propagated `sentry-sample_rand` baggage values and generate a valid sample random value instead. [(#2077)](https://github.com/getsentry/sentry-php/pull/2077)

## 4.25.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.25.0.

### Features

- Add `ExceptionToSentryIssueHandler` Monolog handler that only captures exceptions as Sentry issues without converting log messages to errors. [(#2061)](https://github.com/getsentry/sentry-php/pull/2061)
- Add `metric_flush_threshold` option to automatically flush buffered metrics after a configured number of metric records. [(#2059)](https://github.com/getsentry/sentry-php/pull/2059)

```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
    'metric_flush_threshold' => 50,
]);
```

### Bug Fixes

- Prevent PHP warnings when trying to increase the memory limit for out-of-memory error handling. [(#2063)](https://github.com/getsentry/sentry-php/pull/2063)

### Misc

- Use a `RingBuffer` for log storage when `log_flush_threshold` is not set to prevent unbounded memory growth, with a hard cap of 1000 records. [(#2058)](https://github.com/getsentry/sentry-php/pull/2058)
- Add `ext-excimer` as a Composer suggestion to surface its requirement for profiling. [(#2057)](https://github.com/getsentry/sentry-php/pull/2057)

## 4.24.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.24.0.

### Bug Fixes

- Serialize native PHP enums as readable enum strings, including backed enum values, instead of opaque `Object` strings. [(#2038)](https://github.com/getsentry/sentry-php/pull/2038)
- Exclude `AGENTS.md` and `CLAUDE.md` from distribution archives. [(#2046)](https://github.com/getsentry/sentry-php/pull/2046)

### Misc

- Deprecate `Sentry\Monolog\Handler` in favor of `Sentry\Monolog\LogsHandler` with the `enable_logs` SDK option. [(#2051)](https://github.com/getsentry/sentry-php/pull/2051)

## 4.23.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.23.1.

### Bug Fixes

- Use `server.address` log attribute instead of `sentry.server.address`. [(#2040)](https://github.com/getsentry/sentry-php/pull/2040)

## 4.23.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.23.0.

### Features

- Add `OTLPIntegration` support to interoperate with OpenTelemetry traces. [(#2030)](https://github.com/getsentry/sentry-php/pull/2030)

```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
    'integrations' => [
        new \Sentry\Integration\OTLPIntegration(),
    ],
]);
```

- Add `log_flush_threshold` to automatically flush buffered logs after a configured number of log records. [(#2032)](https://github.com/getsentry/sentry-php/pull/2032)
```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
    'enable_logs' => true,
    'log_flush_threshold' => 20,
]);
```


## 4.22.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.22.0.

### Features

- Add support for the client report protocol without collecting client reports yet. [(#1978)](https://github.com/getsentry/sentry-php/pull/1978)
- Add `strict_trace_continuation` support to only continue incoming traces when the upstream baggage `org_id` matches the SDK org ID. [(#2016)](https://github.com/getsentry/sentry-php/pull/2016)

Example:
```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
    'strict_trace_continuation' => true,
]);
```

### Bug Fixes

- Preserve sub-second timestamps for Monolog breadcrumbs. [(#2018)](https://github.com/getsentry/sentry-php/pull/2018)

## 4.21.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.21.0.

### Features

- Add `RuntimeContext` and context lifecycle helpers for long-lived runtimes such as FrankenPHP and RoadRunner. [(#2011)](https://github.com/getsentry/sentry-php/pull/2011)

Long-lived worker runtimes keep process memory between requests, which can cause scope data to leak from one request to the next.
`RuntimeContext` isolates SDK state per request and flushes buffered telemetry when the request context ends.
Data configured before a runtime context is started is copied into each new context as baseline scope data.

Example:

```php
\Sentry\init([
    'dsn' => '__YOUR_DSN__',
]);

$handler = static function (): void {
    \Sentry\withContext(static function (): void {
        // Handle one request.
    });
};

while (frankenphp_handle_request($handler)) {}
```

When using a runtime context, manual `\Sentry\flush()` calls are not needed for request teardown.
It is still necessary to finish transactions explicitly.

## 4.20.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.20.0.

### Features

- Add a high-level `flush()` helper that flushes all buffered telemetry resources (logs and trace metrics). [(#1989)](https://github.com/getsentry/sentry-php/pull/1989)
- Add share handles to cURL (persistent for PHP >= 8.5, non-persistent otherwise). [(#1996)](https://github.com/getsentry/sentry-php/pull/1996)
- Handle HTTP 413 responses explicitly with a dedicated `content_too_large` status. [(#2008)](https://github.com/getsentry/sentry-php/pull/2008)

### Bug Fixes

- Normalize Spotlight URLs to optionally allow trailing `/stream`. [(#1984)](https://github.com/getsentry/sentry-php/pull/1984)
- Monolog messages are now filtered by their original Monolog level before being mapped to Sentry log levels. [(#1992)](https://github.com/getsentry/sentry-php/pull/1992)
- Handle bracketed IPv6 addresses (such as `[::1]`). [(#2007)](https://github.com/getsentry/sentry-php/pull/2007)
- Ignore propagated baggage `sample_rate` when no `sentry-trace` header is present. [(#2002)](https://github.com/getsentry/sentry-php/pull/2002)

### Misc

- Add the `traceMetrics()` helper and deprecate `trace_metrics()`. [(#1995)](https://github.com/getsentry/sentry-php/pull/1995)

## 4.19.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.19.1.

### Bug Fixes

- Don't cast metrics value to `float` in constructor, drop invalid metrics instead. [(#1981)](https://github.com/getsentry/sentry-php/pull/1981)

## 4.19.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.19.0.

### Features

- Add support for metrics. [(#1968)](https://github.com/getsentry/sentry-php/pull/1968)
```php
// Counter metric
\Sentry\trace_metrics()->count('test-counter', 10, ['my-attribute' => 'foo']);

// Gauge metric
\Sentry\trace_metrics()->gauge('test-gauge', 50.0, ['my-attribute' => 'foo'], \Sentry\Unit::millisecond());

// Distribution metric
\Sentry\trace_metrics()->distribution('test-distribution', 20.0, ['my-attribute' => 'foo'], \Sentry\Unit::kilobyte());

// Flush metrics
\Sentry\trace_metrics()->flush();
```

### Bug Fixes

- Add rate limiting for profiles and cron check-ins. [(#1970)](https://github.com/getsentry/sentry-php/pull/1970)
- Fix Spotlight so it always registers the error integrations and emits transport logs even when no DSN is configured. [(#1964)](https://github.com/getsentry/sentry-php/pull/1964)

## 4.18.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.18.1.

### Misc

- Add `addFeatureFlag` helper function. [(#1960)](https://github.com/getsentry/sentry-php/pull/1960)
```php
\Sentry\addFeatureFlag("my.feature.enabled", true);
```

## 4.18.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.18.0.

### Features

- Add support for feature flags. [(#1951)](https://github.com/getsentry/sentry-php/pull/1951)
```php
\Sentry\SentrySdk::getCurrentHub()->configureScope(function (\Sentry\State\Scope $scope) {
    $scope->addFeatureFlag("my.feature.enabled", true);
});
```
- Add more representations for log attributes instead of dropping them. [(#1950)](https://github.com/getsentry/sentry-php/pull/1950)

### Misc

- Merge log attributes in a separate method. [(#1931)](https://github.com/getsentry/sentry-php/pull/1931)

## 4.17.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.17.1.

### Misc

- Call `curl_close` only on PHP version 7.4 and below to prevent deprecation warnings. [(#1947)](https://github.com/getsentry/sentry-php/pull/1947)

## 4.17.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.17.0.

### Bug Fixes

- Empty strings will no longer display `<encoding error>` when serialized. [(#1940)](https://github.com/getsentry/sentry-php/pull/1940)

### Misc

- Remove `symfony/phpunit-bridge` as a dev dependency. [(#1930)](https://github.com/getsentry/sentry-php/pull/1930)
- Update `sentry.origin` to be consistent with other SDKs. [(#1938)](https://github.com/getsentry/sentry-php/pull/1938)

## 4.16.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.16.0.

### Features

- Remove `max_breadcrumbs` limit. [(#1890)](https://github.com/getsentry/sentry-php/pull/1890)
- Implement `__destruct` in `LogsHandler` to make sure logs are always flushed. [(#1916)](https://github.com/getsentry/sentry-php/pull/1916)

### Bug Fixes

- Use PSR log level when logging messages using the PSR-3 logger within the SDK. [(#1907)](https://github.com/getsentry/sentry-php/pull/1907)
- Remove `@internal` annotation from `Sentry\Transport\Result`. [(#1904)](https://github.com/getsentry/sentry-php/pull/1904)

### Misc

- Add `sentry.origin` attribute to `LogsHandler`. [(#1917)](https://github.com/getsentry/sentry-php/pull/1917)

## 4.15.2

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.15.2.

### Bug Fixes

- Ensure the Monolog handler only processes records permitted by their log level. [(#1888)](https://github.com/getsentry/sentry-php/pull/1888)

## 4.15.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.15.1.

### Bug Fixes

- Do not send `template` attribute with logs when there are no template values [(#1885)](https://github.com/getsentry/sentry-php/pull/1885)

## 4.15.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.15.0.

### Features

- Add Monolog Sentry Logs handler [(#1867)](https://github.com/getsentry/sentry-php/pull/1867)

  This new handler allows you to capture Monolog logs as Sentry logs. To use it, configure your Monolog logger:

  ```php
  use Monolog\Logger;
  use Sentry\Monolog\LogsHandler;
  use Sentry\Logs\LogLevel;

  // Initialize Sentry SDK first (make sure 'enable_logs' is set to true)
  \Sentry\init([
      'dsn' => '__YOUR_DSN__',
      'enable_logs' => true,
  ]);

  // Create a Monolog logger
  $logger = new Logger('my-app');

  // Add the Sentry logs handler
  // Optional: specify minimum log level (defaults to LogLevel::debug())
  $handler = new LogsHandler(LogLevel::info());
  $logger->pushHandler($handler);

  // Now your logs will be sent to Sentry
  $logger->info('User logged in', ['user_id' => 123]);
  $logger->error('Payment failed', ['order_id' => 456]);
  ```

  Note: The handler will not collect logs for exceptions (they should be handled separately via `captureException`).

### Bug Fixes

- Fix non string indexed attributes passed as log attributes [(#1882)](https://github.com/getsentry/sentry-php/pull/1882)
- Use correct `sample_rate` key when deriving sampleRand [(#1874)](https://github.com/getsentry/sentry-php/pull/1874)
- Do not call `Reflection*::setAccessible()` in PHP >= 8.1 [(#1872)](https://github.com/getsentry/sentry-php/pull/1872)

## 4.14.2

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.14.2.

### Bug Fixes

- Add missing sample rates in the envelope header [(#1870)](https://github.com/getsentry/sentry-php/pull/1870)

## 4.14.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.14.1.

### Bug Fixes

- Fix missing user attributes on logs [(#1864)](https://github.com/getsentry/sentry-php/pull/1864)

## 4.14.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.14.0.

### Features

- Serialize enum variants with the variant name [(#1860)](https://github.com/getsentry/sentry-php/pull/1860)

### Bug Fixes

- Fix handling of backtrace frames [(#1862)](https://github.com/getsentry/sentry-php/pull/1862)
- Set allowed types for `http_ssl_native_ca` [(#1858)](https://github.com/getsentry/sentry-php/pull/1858)

## 4.13.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.13.0.

### Features

- Add regex support for `ignore_exceptions` and `ignore_transactions` [(#1850)](https://github.com/getsentry/sentry-php/pull/1850)

  You can now use regular expressions to ignore exceptions and transactions:

  ```php
  Sentry\init([
      'ignore_exceptions' => [
          '/.*ArgumentException$/',
      ],
      'ignore_transactions' => [
          '/^GET \/api\/users\/\d+$/',
      ],
  ]);
  ```

- Add support for variadic parameters and null values [(#1849)](https://github.com/getsentry/sentry-php/pull/1849)

### Bug Fixes

- Fix `Options::setEnableLogs` [(#1852)](https://github.com/getsentry/sentry-php/pull/1852)
- Fix `vsprintf` not handling errors [(#1855)](https://github.com/getsentry/sentry-php/pull/1855)

## 4.12.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.12.0.

### Features

- Add support for Sentry Structured Logs [(#1813)](https://github.com/getsentry/sentry-php/pull/1813)

  You can now send logs directly to Sentry using the new logging API:

  ```php
  Sentry\init([
      // Enable logs to be sent to Sentry
      'enable_logs' => true,
  ]);
  ```

  ```php
  use function Sentry\logger;

  // Log messages at different levels
  logger()->info('User logged in', ['user_id' => 123]);
  logger()->warn('Deprecated function used', ['function' => 'old_function']);
  logger()->error('Database connection failed', ['host' => 'db.example.com']);
  logger()->fatal('Critical system failure: %s', ['Out of memory'], ['component' => 'database']);

  // Flush logs to Sentry
  logger()->flush();

  // We recommend registering the flushing in a shutdown function
  register_shutdown_function(static fn () => logger()->flush());
  ```

  To learn more, head over to our [docs](https://docs.sentry.io/platforms/php/logs/).

### Bug Fixes

- Log correct source of sampling decision [(#1836)](https://github.com/getsentry/sentry-php/pull/1836)

## 4.11.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.11.1.

### Bug Fixes

- Fix stripping prefixes from closure frames for PHP 8.4 and up [(#1828)](https://github.com/getsentry/sentry-php/pull/1828)

## 4.11.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.11.0.

### Features

- Serialize `\DateTimeInterface` objects by default [(#1803)](https://github.com/getsentry/sentry-php/pull/1803)
- Add support for [Propagated Ramdom Value](https://develop.sentry.dev/sdk/telemetry/traces/#propagated-random-value) [(#1793)](https://github.com/getsentry/sentry-php/pull/1793)
- Use the `SENTRY_SPOTLIGHT` environment variable as the input for the `spotlight` configuration option [(#1789)](https://github.com/getsentry/sentry-php/pull/1789)

### Bug Fixes

- Fix cases where anonymous stacktrace frames did not get their prefixes stripped [(#1820)](https://github.com/getsentry/sentry-php/pull/1820)
- Fix Guzzle middleware not setting the span it created as the current span, which resulted in nesting issues in the trace view [(#1801)](https://github.com/getsentry/sentry-php/pull/1801)

## 4.10.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.10.0.

### Features

- The SDK was updated to support PHP 8.4 [(#1760)](https://github.com/getsentry/sentry-php/pull/1760)
- Expose a new `http_ssl_native_ca` option to tell the HTTP client to use the operating system's native CA store for certificate verification [(#1766)](https://github.com/getsentry/sentry-php/pull/1766)

### Bug Fixes

- Fix the `http_timeout` & `http_connect_timeout` options, which now also work with sub second values [(#1785)](https://github.com/getsentry/sentry-php/pull/1785)

### Misc

- HTTP breadcrumbs created by the `GuzzleTracingMiddleware` are now set to a warning status for `4xx` responses and an error status for `5xx` responses [(#1773)](https://github.com/getsentry/sentry-php/pull/1773)
- All public Metrics APIs are now no-op, intneral APIs were removed [(#1786)](https://github.com/getsentry/sentry-php/pull/1786)

## 4.9.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.9.0.

### Features

- Allow retrieving a single piece of data from the span by it's key [(#1767)](https://github.com/getsentry/sentry-php/pull/1767)

  ```php
  \Sentry\SentrySdk::getCurrentHub()->getSpan()?->setData([
      'failure' => $span->getData('failure', 0) + 1,
  ]);
  ```

- Add span trace origin [(#1769)](https://github.com/getsentry/sentry-php/pull/1769)

## 4.8.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.8.1.

### Bug Fixes

- Guard against empty `REMOTE_ADDR` [(#1751)](https://github.com/getsentry/sentry-php/pull/1751)

## 4.8.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.8.0.

### Features

- Add timing span when emiting a timing metric [(#1717)](https://github.com/getsentry/sentry-php/pull/1717)

  ```php
  use function Sentry\metrics;

  // This will now both emit a distribution metric and a span with the "expensive-operation" key
  metrics()->timing(
      key: 'expensive-operation',
      callback: fn() => doExpensiveOperation(),
  );
  ```

### Bug Fixes

- Fix missing data on HTTP spans [(#1735)](https://github.com/getsentry/sentry-php/pull/1735)
- Test span sampled status before creating child spans [(#1740)](https://github.com/getsentry/sentry-php/pull/1740)

### Misc

- Implement fast path for ignoring errors [(#1737)](https://github.com/getsentry/sentry-php/pull/1737)
- Add array shape for better autocomplete of `Sentry\init` function [(#1738)](https://github.com/getsentry/sentry-php/pull/1738)
- Represent callable strings as strings [(#1741)](https://github.com/getsentry/sentry-php/pull/1741)
- Use `AWS_LAMBDA_FUNCTION_VERSION` environment variable for release if available [(#1742)](https://github.com/getsentry/sentry-php/pull/1742)

## 4.7.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.7.0.

### Features

- Improve debugging experience by emitting more logs from the SDK [(#1705)](https://github.com/getsentry/sentry-php/pull/1705)
- Handle `metric_bucket` rate limits [(#1726)](https://github.com/getsentry/sentry-php/pull/1726) & [(#1728)](https://github.com/getsentry/sentry-php/pull/1728)

### Bug Fixes

- Fix deprecation notice when trying to serialize a callable [(#1732)](https://github.com/getsentry/sentry-php/pull/1732)

### Misc

- Deprecated `SpanStatus::resourceExchausted()`. Use `SpanStatus::resourceExhausted()` instead [(#1725)](https://github.com/getsentry/sentry-php/pull/1725)
- Update metric normalization [(#1729)](https://github.com/getsentry/sentry-php/pull/1729)

## 4.6.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.6.1.

### Bug Fixes

- Always add the sampled flag to the W3C `traceparent` header [(#1713)](https://github.com/getsentry/sentry-php/pull/1713)
- Add `JSON_ERROR_NON_BACKED_ENUM` to allowed `JSON::encode()` errors. [(#1707)](https://github.com/getsentry/sentry-php/pull/1707)

## 4.6.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.6.0.

### Features

- Add the PHP SAPI to the runtime context [(#1700)](https://github.com/getsentry/sentry-php/pull/1700)

### Bug Fixes

- Correctly apply properties/options in `ClientBuilder::class` [(#1699)](https://github.com/getsentry/sentry-php/pull/1699)
- Attach `_metrics_summary` to transactions [(#1702)](https://github.com/getsentry/sentry-php/pull/1702)

### Misc

- Remove `final` from `Metrics::class` [(#1697)](https://github.com/getsentry/sentry-php/pull/1697)
- Return early when using `ignore_exceptions` [(#1701)](https://github.com/getsentry/sentry-php/pull/1701)
- Attach exceptions to the log message from `FrameContextifierIntegration::class` [(#1678)](https://github.com/getsentry/sentry-php/pull/1678)

## 4.5.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.5.0.

### Features

- Add `before_send_check_in` and `before_send_metrics` [(#1690)](https://github.com/getsentry/sentry-php/pull/1690)

  ```php
  \Sentry\init([
      'before_send_check_in' => function (\Sentry\Event $event) {
          $checkIn = $event->getCheckIn(),
          // modify the check-in or return null to not send it
      },
  ]);
  ```

  ```php
  \Sentry\init([
      'before_send_metrics' => function (\Sentry\Event $event) {
          $metrics = $event->getMetrics(),
          // modify the metrics or return null to not send it
      },
  ]);
  ```

### Bug Fixes

- Fix `_metrics_summary` formatting [(#1682)](https://github.com/getsentry/sentry-php/pull/1682)

- Fix `DebugFileLogger` and `DebugStdOutLogger` to be usable with PHP 7.2 and up [(#1691)](https://github.com/getsentry/sentry-php/pull/1691)

- Allow whitespace in metric tag values [(#1692)](https://github.com/getsentry/sentry-php/pull/1692)

## 4.4.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.4.0.

### Features

- Add `metrics()->timing()` [(#1670)](https://github.com/getsentry/sentry-php/pull/1670)

  This allows you to emit a distribution metric based on the duration of the provided callback.

  ```php
  use function Sentry\metrics;

  metrics()->timing(
      key: 'my-metric',
      callback: fn() => doSomething(),
  );
  ```

- Add `withMonitor()` [(#1679)](https://github.com/getsentry/sentry-php/pull/1679)

  This wraps a callback into monitor check-ins.

  ```php
  use function Sentry\withMonitor;

  withMonitor(
      slug: 'my-monitor',
      callback: fn () => doSomething(),
      monitorConfig: new MonitorConfig(...),
  );
  ```

- Add new `failure_issue_threshold` and `recovery_threshold` configuration to `MonitorConfig` [(#1685)](https://github.com/getsentry/sentry-php/pull/1685)

- Add `TransactionContext::make()` and `SpanContext::make()` [(#1684)](https://github.com/getsentry/sentry-php/pull/1684)

  ```php
  use Sentry\Tracing\SpanContext;

  $spanCpntext = SpanContext::make()
      ->setOp('http.client')
      ->setDescription('GET https://example.com')
  ```
- Add support for fluent use of `Transaction::setName()` [(#1687)](https://github.com/getsentry/sentry-php/pull/1687)

- Add support for the W3C `traceparent` header [(#1680)](https://github.com/getsentry/sentry-php/pull/1680)

### Bug Fixes

- Do not send an empty event if no metrics are in the bucket [(#1676)](https://github.com/getsentry/sentry-php/pull/1676)

- Fix the `http_ssl_verify_peer` option to set the correct value to `CURLOPT_SSL_VERIFYPEER` [(#1686)](https://github.com/getsentry/sentry-php/pull/1686)

### Misc

- Depreacted `UserDataBag::getSegment()` and `UserDataBag::setSegment()`. You may use a custom tag or context instead [(#1681)](https://github.com/getsentry/sentry-php/pull/1681)

## 4.3.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.3.1.

### Bug Fixes

- Fix tags not being serialized correctly for metrics [(#1672)](https://github.com/getsentry/sentry-php/pull/1672)

### Misc

- Remove `@internal` annotation from `MetricsUnit` class [(#1671)](https://github.com/getsentry/sentry-php/pull/1671)

## 4.3.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.3.0.

### Features

- Add support for Sentry Developer Metrics [(#1619)](https://github.com/getsentry/sentry-php/pull/1619)

  ```php
  use function Sentry\metrics;

  // Add 4 to a counter named hits
  metrics()->increment(key: 'hits', value: 4);

  // Add 25 to a distribution named response_time with unit milliseconds
  metrics()->distribution(key: 'response_time', value: 25, unit: MetricsUnit::millisecond());

  // Add 2 to gauge named parallel_requests, tagged with type: "a"
  metrics()->gauge(key: 'parallel_requests, value: 2, tags: ['type': 'a']);

  // Add a user's email to a set named users.sessions, tagged with role: "admin"
  metrics()->set('users.sessions, 'jane.doe@example.com', null, ['role' => User::admin()]);

  // Add 2 to gauge named `parallel_requests`, tagged with `type: "a"`
  Sentry.metrics.gauge('parallel_requests', 2, { tags: { type: 'a' } });

  // Flush the metrics to Sentry
  metrics()->flush();

  // We recommend registering the flushing in a shutdown function
  register_shutdown_function(static fn () => metrics()->flush());
  ```

  To learn more about Sentry Developer Merics, join the discussion at https://github.com/getsentry/sentry-php/discussions/1666.

### Bug Fixes

- Disallow to seralize the `HubAdapter::class` [(#1663)](https://github.com/getsentry/sentry-php/pull/1663)
- Do not overwrite trace context on event [(#1668)](https://github.com/getsentry/sentry-php/pull/1668)
- Serialize breadcrumb data to display correct in the Sentry UI [(#1669)](https://github.com/getsentry/sentry-php/pull/1669)

### Misc

- Remove the `final` keyword from `Hub::class`, `Client::class` and `Scope::class` [(#1665)](https://github.com/getsentry/sentry-php/pull/1665)

## 4.2.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.2.0.

### Features

- Add a config option to allow overriding the Spotlight url [(#1659)](https://github.com/getsentry/sentry-php/pull/1659)

  ```php
  Sentry\init([
      'spotlight_url' => 'http://localhost:8969',
  ]);
  ```

### Bug Fixes

- Restore setting the `logger` value on the event payload [(#1657)](https://github.com/getsentry/sentry-php/pull/1657)

- Only apply the `sample_rate` on error/message events [(#1662)](https://github.com/getsentry/sentry-php/pull/1662)

  This fixes an issue where Cron Check-Ins were wrongly sampled out if a `sample_rate` lower than `1.0` is used.

### Misc

- Remove the `@internal` annotation from `ClientBuilder::class` [(#1661)](https://github.com/getsentry/sentry-php/pull/1661)

## 4.1.0

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.1.0.

### Features

- Add support for Spotlight [(#1647)](https://github.com/getsentry/sentry-php/pull/1647)

  Spotlight is Sentry for Development. Inspired by an old project, Django Debug Toolbar. Spotlight brings a rich debug overlay into development environments, and it does it by leveraging the existing power of Sentry's SDKs.

  To learn more about Spotlight, go to https://spotlightjs.com/.

### Misc

- Normalize `response` status [(#1644)](https://github.com/getsentry/sentry-php/pull/1644)

## 4.0.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.0.1.

### Bug Fixes

- Fix capturing out-of-memory errors when memory-constrained [(#1636)](https://github.com/getsentry/sentry-php/pull/1636)
- Check if the cURL extension is installed [(#1632)](https://github.com/getsentry/sentry-php/pull/1632)

## 4.0.0

The Sentry SDK team is thrilled to announce the immediate availability of Sentry PHP SDK v4.0.0.

### Breaking Change

Please refer to the [UPGRADE-4.0.md](UPGRADE-4.0.md) guide for a complete list of breaking changes.

- This version exclusively uses the [envelope endpoint](https://develop.sentry.dev/sdk/envelopes/) to send event data to Sentry.

  If you are using [sentry.io](https://sentry.io), no action is needed.
  If you are using an on-premise/self-hosted installation of Sentry, the minimum requirement is now version `>= v20.6.0`.

- You need to have `ext-curl` installed to use the SDK.

- The `IgnoreErrorsIntegration` integration was removed. Use the `ignore_exceptions` option instead.

  ```php
  Sentry\init([
      'ignore_exceptions' => [BadThingsHappenedException::class],
  ]);
  ```

  This option performs an [`is_a`](https://www.php.net/manual/en/function.is-a.php) check now, so you can also ignore more generic exceptions.

### Features

- Add new fluent APIs [(#1601)](https://github.com/getsentry/sentry-php/pull/1601)

  ```php
  // Before
  $transactionContext = new TransactionContext();
  $transactionContext->setName('GET /example');
  $transactionContext->setOp('http.server');

  // After
  $transactionContext = (new TransactionContext())
      ->setName('GET /example');
      ->setOp('http.server');
  ```

- Simplify the breadcrumb API [(#1603)](https://github.com/getsentry/sentry-php/pull/1603)

  ```php
  // Before
  \Sentry\addBreadcrumb(
      new \Sentry\Breadcrumb(
          \Sentry\Breadcrumb::LEVEL_INFO,
          \Sentry\Breadcrumb::TYPE_DEFAULT,
          'auth',                // category
          'User authenticated',  // message (optional)
          ['user_id' => $userId] // data (optional)
      )
  );

  // After
  \Sentry\addBreadcrumb(
      category: 'auth',
      message: 'User authenticated', // optional
      metadata: ['user_id' => $userId], // optional
      level: Breadcrumb::LEVEL_INFO, // set by default
      type: Breadcrumb::TYPE_DEFAULT, // set by default
  );
  ```

- New `logger` option [(#1625)](https://github.com/getsentry/sentry-php/pull/1625)

  To make it easier to debug the internals of the SDK, the `logger` option now accepts a `Psr\Log\LoggerInterface` instance.
  We do provide two implementations, `Sentry\Logger\DebugFileLogger` and `Sentry\Logger\DebugStdOutLogger`.

  ```php
  // This logs messages to the provided file path
  Sentry\init([
      'logger' => new DebugFileLogger(filePath: ROOT . DS . 'sentry.log'),
  ]);

  // This logs messages to stdout
  Sentry\init([
      'logger' => new DebugStdOutLogger(),
  ]);
  ```

- New default cURL HTTP client [(#1589)](https://github.com/getsentry/sentry-php/pull/1589)

  The SDK now ships with its own HTTP client based on cURL. A few new options were added.

  ```php
  Sentry\init([
      'http_proxy_authentication' => 'username:password', // user name and password to use for proxy authentication
      'http_ssl_verify_peer' => false, // default true, verify the peer's SSL certificate
      'http_compression' => false, // default true, http request body compression
  ]);
  ```

  To use a different client, you may use the `http_client` option.

  ```php
  use Sentry\Client;
  use Sentry\HttpClient\HttpClientInterface;
  use Sentry\HttpClient\Request;
  use Sentry\HttpClient\Response;
  use Sentry\Options;

  $httpClient = new class() implements HttpClientInterface {
      public function sendRequest(Request $request, Options $options): Response
      {

          // your custom implementation

          return new Response($response->getStatusCode(), $response->getHeaders(), '');
      }
  };

  Sentry\init([
      'http_client' => $httpClient,
  ]);
  ```

  To use a different transport, you may use the `transport` option. A custom transport must implement the `TransportInterface`.
  If you use the `transport` option, the `http_client` option has no effect.

### Misc

- The abandoned package `php-http/message-factory` was removed.
