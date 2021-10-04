# CHANGELOG

## Unreleased

## 3.3.3 (2021-10-04)

-  Fix fatal error in the `EnvironmentIntegration` integration if the `php_uname` function is disabled (#1243)

## 3.3.2 (2021-07-19)

- Allow installation of `guzzlehttp/psr7:^2.0` (#1225)
- Allow installation of `psr/log:^1.0|^2.0|^3.0` (#1229)

## 3.3.1 (2021-06-21)

- Fix missing collecting of frames's arguments when using `captureEvent()` without expliciting a stacktrace or an exception (#1223)

## 3.3.0 (2021-05-26)

- Allow setting a custom timestamp on the breadcrumbs (#1193)
- Add option `ignore_tags` to `IgnoreErrorsIntegration` in order to ignore exceptions by tags values. (#1201)

## 3.2.2 (2021-05-06)

- Fix missing handling of `EventHint` in the `HubAdapter::capture*()` methods (#1206)

## 3.2.1 (2021-04-06)

- Changes behaviour of `error_types` option when not set: before it defaulted to `error_reporting()` statically at SDK initialization; now it will be evaluated each time during error handling to allow silencing errors temporarily (#1196)

## 3.2.0 (2021-03-03)

- Make the HTTP headers sanitizable in the `RequestIntegration` integration instead of removing them entirely (#1161)
- Deprecate the `logger` option (#1167)
- Pass the event hint from the `capture*()` methods down to the `before_send` callback (#1138)
- Deprecate the `tags` option, see the [docs](https://docs.sentry.io/platforms/php/guides/laravel/enriching-events/tags/) for other ways to set tags (#1174)
- Make sure the `environment` field is set to `production` if it has not been overridden explicitly (#1116)

## 3.1.5 (2021-02-18)

- Fix incorrect detection of silenced errors (by the `@` operator) (#1183)

## 3.1.4 (2021-02-02)

- Allow jean85/pretty-package-versions 2.0 (#1170)

## 3.1.3 (2021-01-25)

- Fix the fetching of the version of the SDK (#1169)
- Add the `$customSamplingContext` argument to `Hub::startTransaction()` and `HubAdapter::startTransaction()` to fix deprecations thrown in Symfony (#1176)

## 3.1.2 (2021-01-08)

- Fix unwanted call to the `before_send` callback with transaction events, use `traces_sampler` instead to filter transactions (#1158)
- Fix the `logger` option not being applied to the event object (#1165)
- Fix a bug that made some event attributes being overwritten by option config values when calling `captureEvent()` (#1148)

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
