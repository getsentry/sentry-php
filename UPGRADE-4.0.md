# Upgrade 3.x to 4.0

- The `send_attempts` option was removed. You may implement a custom transport if you rely on this behaviour.
- `SpanContext::fromTraceparent()` was removed. Use `Sentry\continueTrace()` instead.
- `TransactionContext::fromSentryTrace()` was removed. Use `Sentry\continueTrace()` instead.
- The `IgnoreErrorsIntegration` integration was removed. Use the `ignore_errors` option instead.
- `Sentry\Exception\InvalidArgumentException` was removed. Use `\InvalidArgumentException` instead.
- `Sentry\Exception/ExceptionInterface` was removed.
- Removed `ClientBuilderInterface::setSerializer()`
- Removed `ClientBuilder::setSerializer()`
- Removed `Client::__construct()` param SerializerInterface $serializer.
- Change return type of `Dsn:: getProjectId()` to string
