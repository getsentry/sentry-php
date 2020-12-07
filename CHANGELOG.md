# CHANGELOG

## 3.1.1 (2020-12-07)

- Add support for PHP 8.0 (#1087)
- Change the error handling for silenced fatal errors using `@` to use a mask check in order to be php 8 compatible (#1141)
- Update the `guzzlehttp/promises` package to the minimum required version compatible with PHP 8 (#1144)
- Update the `symfony/options-resolver` package to the minimum required version compatible with PHP 8 (#1144)

## 3.1.0 (2020-12-01)

- Fix capturing of the request body in the `RequestIntegration` integration (#1139)
- Deprecate `SpanContext::fromTraceparent()` in favor of `TransactionContext::fromSentryTrace()` (#1134)
- Allow setting custom data on the sampling context by passing it as 2nd argument of the `startTransaction()` function (#1134)
- Add setter for value on the `ExceptionDataBag` (#1100)
- Add `Scope::removeTag` method (#1126)

## 3.0.4 (2020-11-06)

- Fix stacktrace missing from payload for non-exception events (#1123)
- Fix capturing of the request body in the `RequestIntegration` integration when the stream is empty (#1119)

## 3.0.3 (2020-10-12)

- Fix missing source code excerpts for stacktrace frames whose absolute file path is equal to the file path (#1104)
- Fix requirements to construct a valid object instance of the `UserDataBag` class (#1108)

## 3.0.2 (2020-10-02)

- Fix use of the `sample_rate` option rather than `traces_sample_rate` when capturing a `Transaction` (#1106)

## 3.0.1 (2020-10-01)

- Fix use of `Transaction` instead of `Span` in the `GuzzleMiddleware` middleware (#1099)

## 3.0.0 (2020-09-28)

**Tracing API**

In this version we released API for Tracing. `\Sentry\startTransaction` is your entry point for manual instrumentation.
More information can be found in our [Performance](https://docs.sentry.io/platforms/php/performance/) docs.

**Breaking Change**: This version uses the [envelope endpoint](https://develop.sentry.dev/sdk/envelopes/). If you are
using an on-premise installation it requires Sentry version `>= v20.6.0` to work. If you are using
[sentry.io](https://sentry.io) nothing will change and no action is needed.

- [BC BREAK] Remove the deprecated code that made the `Hub` class a singleton (#1038)
- [BC BREAK] Remove deprecated code that permitted to register the error, fatal error and exception handlers at once (#1037)
- [BC BREAK] Change the default value for the `error_types` option from `E_ALL` to the value get from `error_reporting()` (#1037)
- [BC BREAK] Remove deprecated code to return the event ID as a `string` rather than an object instance from the transport, the client and the hub (#1036)
- [BC BREAK] Remove some deprecated methods from the `Options` class. (#1047)
- [BC BREAK] Remove the deprecated code from the `ModulesIntegration` integration (#1047)
- [BC BREAK] Remove the deprecated code from the `RequestIntegration` integration (#1047)
- [BC BREAK] Remove the deprecated code from the `Breadcrumb` class (#1047)
- [BC BREAK] Remove the deprecated methods from the `ClientBuilderInterface` interface and its implementations (#1047)
- [BC BREAK] The `Scope::setUser()` method now always merges the given data with the existing one instead of replacing it as a whole (#1047)
- [BC BREAK] Remove the `Context::CONTEXT_USER`, `Context::CONTEXT_RUNTIME`, `Context::CONTEXT_TAGS`, `Context::CONTEXT_EXTRA`, `Context::CONTEXT_SERVER_OS` constants (#1047)
- [BC BREAK] Use PSR-17 factories in place of the Httplug's ones and return a promise from the transport (#1066)
- [BC BREAK] The Monolog handler does not set anymore tags and extras on the event object (#1068)
- [BC BREAK] Remove the `UserContext`, `ExtraContext` and `Context` classes and refactor the `ServerOsContext` and `RuntimeContext` classes (#1071)
- [BC BREAK] Remove the `FlushableClientInterface` and the `ClosableTransportInterface` interfaces (#1079)
- [BC BREAK] Remove the `SpoolTransport` transport and all its related classes (#1080)
- Add the `EnvironmentIntegration` integration to gather data for the `os` and `runtime` contexts (#1071)
- Refactor how the event data gets serialized to JSON (#1077)
- Add `traces_sampler` option to set custom sample rate callback (#1083)
- [BC BREAK] Add named constructors to the `Event` class (#1085)
- Raise the minimum version of PHP to `7.2` and the minimum version of some dependencies (#1088)
- [BC BREAK] Change the `captureEvent` to only accept an instance of the `Event` class rather than also a plain array (#1094)
- Add Guzzle middleware to trace performance of HTTP requests (#1096)

## 3.0.0-beta1 (2020-09-03)

**Tracing API**

In this version we released API for Tracing. `\Sentry\startTransaction` is your entry point for manual instrumentation.
More information can be found in our [Performance](https://docs.sentry.io/product/performance/) docs or specific
[PHP SDK](https://docs.sentry.io/platforms/php/) docs.

**Breaking Change**: This version uses the [envelope endpoint](https://develop.sentry.dev/sdk/envelopes/). If you are
using an on-premise installation it requires Sentry version `>= v20.6.0` to work. If you are using
[sentry.io](https://sentry.io) nothing will change and no action is needed.

- [BC BREAK] Remove the deprecated code that made the `Hub` class a singleton (#1038)
- [BC BREAK] Remove deprecated code that permitted to register the error, fatal error and exception handlers at once (#1037)
- [BC BREAK] Change the default value for the `error_types` option from `E_ALL` to the value get from `error_reporting()` (#1037)
- [BC BREAK] Remove deprecated code to return the event ID as a `string` rather than an object instance from the transport, the client and the hub (#1036)
- [BC BREAK] Remove some deprecated methods from the `Options` class. (#1047)
- [BC BREAK] Remove the deprecated code from the `ModulesIntegration` integration (#1047)
- [BC BREAK] Remove the deprecated code from the `RequestIntegration` integration (#1047)
- [BC BREAK] Remove the deprecated code from the `Breadcrumb` class (#1047)
- [BC BREAK] Remove the deprecated methods from the `ClientBuilderInterface` interface and its implementations (#1047)
- [BC BREAK] The `Scope::setUser()` method now always merges the given data with the existing one instead of replacing it as a whole (#1047)
- [BC BREAK] Remove the `Context::CONTEXT_USER`, `Context::CONTEXT_RUNTIME`, `Context::CONTEXT_TAGS`, `Context::CONTEXT_EXTRA`, `Context::CONTEXT_SERVER_OS` constants (#1047)
- [BC BREAK] Use PSR-17 factories in place of the Httplug's ones and return a promise from the transport (#1066)
- [BC BREAK] The Monolog handler does not set anymore tags and extras on the event object (#1068)
- [BC BREAK] Remove the `UserContext`, `ExtraContext` and `Context` classes and refactor the `ServerOsContext` and `RuntimeContext` classes (#1071)
- [BC BREAK] Remove the `FlushableClientInterface` and the `ClosableTransportInterface` interfaces (#1079)
- [BC BREAK] Remove the `SpoolTransport` transport and all its related classes (#1080)
- Add the `EnvironmentIntegration` integration to gather data for the `os` and `runtime` contexts (#1071)
- Refactor how the event data gets serialized to JSON (#1077)

### 2.5.0 (2020-09-14)

- Support the `timeout` and `proxy` options for the Symfony HTTP Client (#1084)

### 2.4.3 (2020-08-13)

- Fix `Options::setEnvironment` method not accepting `null` values (#1057)
- Fix the capture of the request body in the `RequestIntegration` integration when the stream size is unknown (#1064)

### 2.4.2 (2020-07-24)

- Fix typehint errors while instantiating the Httplug cURL client by forcing the usage of PSR-17 complaint factories (#1052)

### 2.4.1 (2020-07-03)

- Fix HTTP client connection timeouts not being applied if an HTTP proxy is specified (#1033)
- [BC CHANGE] Revert "Add support for iterables in the serializer (#991)" (#1030)

### 2.4.0 (2020-05-21)

- Enforce a timeout for connecting to the server and for the requests instead of waiting indefinitely (#979)
- Add `RequestFetcherInterface` to allow customizing the request data attached to the logged event (#984)
- Log internal debug and error messages to a PSR-3 compatible logger (#989)
- Make `AbstractSerializer` to accept `Traversable` values using `is_iterable` instead of `is_array` (#991)
- Refactor the `ModulesIntegration` integration to improve its code and its tests (#990)
- Extract the parsing and validation logic of the DSN into its own value object (#995)
- Support passing either a Httplug or PSR-17 stream factory to the `GzipEncoderPlugin` class (#1012)
- Add the `FrameContextifierIntegration` integration (#1011)
- Add missing validation for the `context_lines` option and fix its behavior when passing `null` to make it working as described in the documentation (#1003)
- Trim the file path from the anonymous class name in the stacktrace according to the `prefixes` option (#1016)

## 2.3.2 (2020-03-06)

- Hard-limit concurrent requests in `HttpTransport` and removed pre-init of promises (fixes "too many open files" errors) (#981)
- Fix `http_proxy` option not being applied (#978)
- Fix the error handler rethrowing the captured exception when previous handler didn't (#974)

## 2.3.1 (2020-01-23)

- Allow unsetting the stack trace on an `Event` by calling `Event::setStacktrace(null)` (#961)
- Fix sending of both `event.stacktrace` and `event.exceptions` when `attach_stacktrace = true` (#960)
- Fix regression that set all frames of a stacktrace as not in app by default (#958)
- Fix issues with memory addresses in anonymous class stack traces (#956)
- Fix exception thrown regardless of whether the HTTP client was instantiated when using the `http_proxy option` (#951)

## 2.3.0 (2020-01-08)

- Add `in_app_include` option to whitelist paths that should be marked as part of the app (#909)
- Fix `Client::captureEvent` not considering the `attach_stacktrace` option (#940)
- Replace `ramsey/uuid` dependency with `uuid_create` from the PECL [`uuid`](https://pecl.php.net/package/uuid) extension or [`symfony/polyfill-uuid`](https://github.com/symfony/polyfill-uuid) (#937)
- Deprecate `Scope::setUser` behaviour of replacing user data. (#929)
- Add the `$merge` parameter on `Scope::setUser` to allow merging user context. (#929)
- Make the `integrations` option accept a `callable` that will receive the list of default integrations and returns a customized list (#919)
- Add the `IgnoreErrorsIntegration` integration to deprecate and replace the `exclude_exceptions` option (#928)
- Allow setting custom contexts on the scope and on the event (#839)
- Replace dependency to `zendframework/zend-diactoros` with `guzzlehttp/psr7` (#945)

## 2.2.6 (2019-12-18)

- Fix remaining PHP 7.4 deprecations (#930)
- Fix error thrown during JSON encoding if a string contains invalid UTF-8 characters (#934)

## 2.2.5 (2019-11-27)

- Add compatibility with Symfony 5 (#925)
- Ensure compatibility with PHP 7.4 (#894, #926)

## 2.2.4 (2019-11-04)

- Suggest installing Monolog to send log messages directly to Sentry (#908)
- Make the `$errcontext` argument of the `ErrorHandler::handleError()` method `nullable` (#917)

## 2.2.3 (2019-10-31)

- Fix deprecation raised when serializing callable in certain circumstances (#821)
- Fix incorrect `critical` breadcrumb level by replacing it with the `fatal` level (#901)
- Fix regression on default sending behavior of the `HttpTransport` transport (#905)
- Fix stacktrace frame inApp detection: all paths outside the project_root are now considered as not in app (#911)

## 2.2.2 (2019-10-10)

- Fix handling of fifth argument in the error handler (#892)
- Catch exception from vendors in `Sentry\Transport\HttpTransport` (#899)

## 2.2.1 (2019-09-23)

- Disable default deprecation warning `Sentry\Transport\HttpTransport` (#884)

## 2.2.0 (2019-09-23)

- Change type hint for both parameter and return value of `HubInterface::getCurrentHub` and `HubInterface::setCurrentHub()` methods (#849)
- Add the `setTags`, `setExtras` and `clearBreadcrumbs` methods to the `Scope` class (#852)
- Silently cast numeric values to strings when trying to set the tags instead of throwing (#858)
- Support force sending events on-demand and fix sending of events in long-running processes (#813)
- Update PHPStan and introduce Psalm (#846)
- Add an integration to set the transaction attribute of the event (#865)
- Deprecate `Hub::getCurrent` and `Hub::setCurrent` methods to set the current hub instance (#847)

## 2.1.3 (2019-09-06)

- Fix GZIP-compressed requests failing when `exit($code)` was used to terminate the application (#877)

## 2.1.2 (2019-08-22)

- Fix `TypeError` in `Sentry\Monolog\Handler` when the extra data array has numeric keys (#833).
- Fix sending of GZIP-compressed requests when the `enable_compression` option is `true` (#857)
- Fix error thrown when trying to set the `transaction` attribute of the event in a CLI environment (#862)
- Fix integrations that were not skipped if the client bound to the current hub was not using them (#861)
- Fix undefined index generated by missing function in class (#823)

## 2.1.1 (2019-06-13)

- Fix the behavior of the `excluded_exceptions` option: now it's used to skip capture of exceptions, not to purge the
  `exception` data of the event, which resulted in broken or empty chains of exceptions in reported events (#822)
- Fix handling of uploaded files in the `RequestIntegration`, to respect the PSR-7 spec fully (#827)
- Fix use of `REMOTE_ADDR` server variable rather than HTTP header
- Fix exception, open_basedir restriction in effect (#824)

## 2.1.0 (2019-05-22)

- Mark Sentry internal frames when using `attach_stacktrace` as `in_app` `false` (#786)
- Increase default severity of `E_RECOVERABLE_ERROR` to `Severity::ERROR`, instead of warning (#792)
- Make it possible to register fatal error listeners separately from the error listeners
  and change the type of the reported exception to `\Sentry\Exception\FatalErrorException` (#788)
- Add a static factory method to create a breadcrumb from an array of data (#798)
- Add support for `SENTRY_ENVRIONMENT` and `SENTRY_RELEASE` environment variables (#810)
- Add the `class_serializers` option to make it possible to customize how objects are serialized in the event payload (#809)
- Fix the default value of the `$exceptions` property of the Event class (#806)
- Add a Monolog handler (#808)
- Allow capturing the body of an HTTP request (#807)
- Capture exceptions during serialization, to avoid hard failures (#818)

## 2.0.1 (2019-03-01)

- Do no longer report silenced errors by default (#785)
- New option `capture_silenced_error` to enable reporting of silenced errors, disabled by default (#785)

## 2.0.0 (2019-02-25)

**Version 2.0.0 is a complete rewrite of the existing SDK. Code Changes are needed. Please see [UPGRADE 2.0](https://github.com/getsentry/sentry-php/blob/master/UPGRADE-2.0.md) for more details.**

- Updated .gitattributes to reduce package footprint (#770)
- Use multibyte functions to handle unicode paths (#774)
- Remove `Hub::getScope()` to deny direct access to `Scope` instances (#776)
- Reintroduce `http_proxy` option (#775)
- Added support for HTTPlug 2 / PSR-18 (#777)

## 2.0.0-beta2 (2019-02-11)
- Rename `SentryAuth` class to `SentryAuthentication` (#742)
- `Client` class is now final
- Fix issue with `ClientBuilder`: factories are not instantiated if transport is set manually (#747)
- Rename `excluded_paths` to `in_app_exclude` option to follow Unified API spec (#755)
- Add `max_value_length` option to trim long values during serialization (#754)
- Lower the default `send_attempts` to 3 (#760)
- Fix method argument name handling when Xdebug is enabled (#763)
- Add CI build under Windows with AppVeyor (#758) and fix some bugs
- Change the `ErrorHandler` and default integrations behavior: the handler is now a singleton,
  and it's possible to attach a number of callables as listeners for errors and exceptions (#762)
- The `context_lines` options changed the default to `5` and is properly applied (#743)
- Add support for "formatted messages" in `captureEvent` as payload (#752)
- Fix issue when capturing exceptions to remove warning when converting array args (#761)

## 2.0.0-beta1 (2018-12-19)

- Require PHP >= 7.1
- Refactor the whole codebase to support the Unified API SDK specs
- See the UPGRADE.md document for more information.

## 1.10.0 (2018-11-09)

- Added passing data from context in monolog breadcrumb handler (#683)
- Do not return error id if we know we did not send the error (#667)
- Do not force IPv4 protocol by default (#654)

## 1.9.2 (2018-08-18)

- Remove secret_key from required keys for CLI test command. (#645)
- Proper case in Raven_Util class name usage. (#642)
- Support longer credit card numbers. (#635)
- Use configured message limit when creating serializers. (#634)
- Do not truncate strings if message limit is set to zero. (#630)
- Add option to ignore SERVER_PORT getting added to url. (#629)
- Cleanup the PHP version reported. (#604)

## 1.9.1 (2018-06-19)

- Allow the use of a public DSN (private part of the DSN was deprecated in Sentry 9) (#615)
- Send transaction as transaction not as culprit (#601)

## 1.9.0 (2018-05-03)

- Fixed undefined variable (#588)
- Fix for exceptions throwing exceptions when setting event id (#587)
- Fix monolog handler not accepting Throwable (#586)
- Add `excluded_exceptions` option to exclude exceptions and their extending exceptions (#583)
- Fix `HTTP_X_FORWARDED_PROTO` header detection (#578)
- Fix sending events async in PHP 5 (#576)
- Avoid double reporting due to `ErrorException`s (#574)
- Make it possible to overwrite serializer message limit of 1024 (#559)
- Allow request data to be nested up to 5 levels deep (#554)
- Update serializer to handle UTF-8 characters correctly (#553)

## 1.8.4 (2018-03-20)

- Revert ignoring fatal errors on PHP 7+ (#571)
- Add PHP runtime information (#564)
- Cleanup the `site` value if it's empty (#555)
- Add `application/json` input handling (#546)

## 1.8.3 (2018-02-07)

- Serialize breadcrumbs to prevent issues with binary data (#538)
- Fix notice array_key_exists() expects parameter 2 to be array, null given (#527)

## 1.8.2 (2017-12-21)

- Improve handling DSN with "null" like values (#522)
- Prevent warning in Raven_Stacktrace (#493)

## 1.8.1 (2017-11-09)

- Add setters for the serializers on the `Raven_Client` (#515)
- Avoid to capture `E_ERROR` in PHP 7+, because it's also a `Throwable` that gets captured and duplicates the error (#514)

## 1.8.0 (2017-10-29)

- Use namespaced classes in test for PHPUnit (#506)
- Prevent segmentation fault on PHP `<5.6` (#504)
- Remove `ini_set` call for unneeded functionality (#501)
- Exclude single `.php` files from the app path (#500)
- Start testing PHP 7.2 (#489)
- Exclude anonymous frames from app path (#482)

## 1.7.1 (2017-08-02)

- Fix of filtering sensitive data when there is an exception with multiple 'values' (#483)

## 1.7.0 (2017-06-07)

- Corrected some issues with argument serialization in stacktraces (#399).
- The default exception handler will now re-raise exceptions when `call_existing` is true and no exception handler is registered (#421).
- Collect `User.ip_address` automatically (#419).
- Added a processor to remove web cookies. It will be enabled by default in `2.0` (#405).
- Added a processor to remove HTTP body data for POST, PUT, PATCH and DELETE requests. It will be enabled by default in `2.0` (#405).
- Added a processor to sanitize HTTP headers (e.g. the Authorization header) (#428).
- Added a processor to remove `pre_context`, `context_line` and `post_context` informations from reported exceptions (#429).

## 1.6.2 (2017-02-03)

- Fixed behavior where fatal errors weren't correctly being reported in most situations.

## 1.6.1 (2016-12-14)

- Correct handling of null in `user_context`.

## 1.6.0 (2016-12-09)

- Improved serialization of certain types to be more restrictive.
- `error_types` can now be configured via `RavenClient`.
- Class serialization has been expanded to include attributes.
- The session extension is no longer required.
- Monolog is no longer a required dependency.
- `user_context` now merges by default.

## 1.5.0 (2016-09-29)

- Added named transaction support.

## 1.4.0 (2016-09-20)

This version primarily overhauls the exception/stacktrace generation to fix
a few bugs and improve the quality of data (#359).

- Added `excluded_app_paths` config.
- Removed `shift_vars` config.
- Correct fatal error handling to only operate on expected types. This also fixes some behavior with the error suppression operator.
- Expose anonymous and similar frames in the stacktrace.
- Default `prefixes` to PHP's include paths.
- Remove `module` usage.
- Better handle empty argument context.
- Correct alignment of filename (current frame) and function (caller frame)

## 1.3.0 (2016-12-19)

- Fixed an issue causing the error suppression operator to not be respected (#335)
- Fixed some serialization behavior (#352)
- Fixed an issue with app paths and trailing slashes (#350)
- Handle non-latin encoding with source code context line (#345)

## 1.2.0 (2016-12-08)

- Handle non-latin encoding in source code and exception values (#342)
- Ensure pending events are sent on shutdown by default (#338)
- Add `captureLastError` helper (#334)
- Dont report duplicate errors with fatal error handler (#334)
- Enforce maximum length for string serialization (#329)

## 1.1.0 (2016-07-30)

- Uncoercable values should no longer prevent exceptions from sending
  to the Sentry server.
- `install()` can no longer be called multiple times.

## 1.0.0 (2016-07-28)

- Removed deprecated error codes configuration from ErrorHandler.
- Removed env data from HTTP interface.
- Removed `message` attribute from exceptions.
- appPath and prefixes are now resolved fully.
- Fixed various getter methods requiring invalid args.
- Fixed data mutation with `send_callback`.

## 0.22.0 (2016-06-23)

- Improve handling of encodings.
- Improve resiliency of variable serialization.
- Add 'formatted' attribute to Message interface.

## 0.21.0 (2016-06-10)

- Added `transport` option.
- Added `install()` shortcut.

## 0.20.0 (2016-06-02)

- Handle missing function names on frames.
- Remove suppression operator usage in breadcrumbs buffer.
- Force serialization of context values.

## 0.19.0 (2016-05-27)

- Add `error_reporting` breadcrumb handler.

## 0.18.0 (2016-05-17)

- Remove session from serialized data.
- `send_callback` return value must now be false to prevent capture.
- Add various getter/setter methods for configuration.

## 0.17.0 (2016-05-11)

- Don't attempt to serialize fixed SDK inputs.
- Improvements to breadcrumbs support in Monolog.

## 0.16.0 (2016-05-03)

- Initial breadcrumbs support with Monolog handler.

## 0.15.0 (2016-04-29)

- Fixed some cases where serialization wouldn't happen.
- Added sdk attribute.

## 0.14.0 (2016-04-27)

- Added `prefixes` option for stripping absolute paths.
- Removed `abs_path` from stacktraces.
- Added `app_path` to specify application root for resolving `in_app` on frames.
- Moved Laravel support to `sentry-laravel` project.
- Fixed duplicate stack computation.
- Added `dsn` option to ease configuration.
- Fixed an issue with the curl async transport.
- Improved serialization of values.

## 0.13.0 (2015-09-09)

- Updated API to use new style interfaces.
- Remove session cookie in default processor.
- Expand docs for Laravel, Symfony2, and Monolog.
- Default error types can now be set as part of ErrorHandler configuration.

## 0.12.1 (2015-07-26)

- Dont send empty values for various context.

## 0.12.0 (2015-05-19)

- Bumped protocol version to 6.
- Fixed an issue with the async curl handler (GH-216).
- Removed UDP transport.

## 0.11.0 (2015-03-25)

- New configuration parameter: `release`
- New configuration parameter: `message_limit`
- New configuration parameter: `curl_ssl_version`
- New configuration parameter: `curl_ipv4`
- New configuration parameter: `verify_ssl`
- Updated remote endpoint to use modern project-based path.
- Expanded default sanitizer support to include `auth_pw` attribute.

## 0.10.0 (2014-09-03)

- Added a default certificate bundle which includes common root CA's as well as getsentry.com's CA.

## 0.9.1 (2014-08-26)

- Change default curl connection to `sync`
- Improve CLI reporting

## 0.9.0 (2014-06-04)

- Protocol version 5
- Default to asynchronous HTTP handler using curl_multi.


(For previous versions see the commit history)
