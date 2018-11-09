# CHANGELOG

## Unreleased

- ...

## 1.10.0 (2018-11-09)

- Added passing data from context in monolog breadcrumb handler (#683)
- Do not return error id if we know we did not send the error (#667)
- Do not force IPv4 protocol by default (#654)

## 1.9.2 (2018-08-17)

- Remove secret_key from required keys for CLI test command. (#645)
- Proper case in Raven_Util class name usage. (#642)
- Support longer creditcard numbers. (#635)
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
