# Upgrade 2.x to 3.0

- Removed the `HubInterface::getCurrentHub()` and `HubInterface::setCurrentHub()` methods. Use `SentrySdk::getCurrentHub()` and `SentrySdk::setCurrentHub()` instead.
- Removed the `ErrorHandler::registerOnce()` method, use `ErrorHandler::register*Handler()` instead.
- Removed the `ErrorHandler::addErrorListener` method, use `ErrorHandler::addErrorHandlerListener()` instead.
- Removed the `ErrorHandler::addFatalErrorListener` method, use `ErrorHandler::addFatalErrorHandlerListener()` instead.
- Removed the `ErrorHandler::addExceptionListener` method, use `ErrorHandler::addExceptionHandlerListener()` instead.
- The signature of the `ErrorListenerIntegration::__construct()` method changed to not accept any parameter
- The signature of the `FatalErrorListenerIntegration::__construct()` method changed to not accept any parameter
- The `ErrorListenerIntegration` integration does not get called anymore when a fatal error occurs
- The default value of the `error_types` option changed to the value get from `error_reporting()`
- The signature of the `capture*()` global functions changed to return an instance of the `Sentry\EventId` class instead of a `string`
- The signature of the `ClientInterface::capture*()` methods changed to return an instance of the `Sentry\EventId` class instead of a `string`
- The signature of the `HubInterface::capture*e()` methods changed to return an instance of the `Sentry\EventId` class instead of a `string`
- The signature of the `Event::getId()` method changed to return an instance of the `Sentry\EventId` class instead of a `string`
