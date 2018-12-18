# Upgrade from 1.7 to 2.0

### Client options

- The `environment` option has been renamed to `current_environment`.
- The `http_proxy` option has been renamed to `proxy`.
- The `processorOptions` option has been renamed to `processors_options`.
- The `exclude` option has been renamed to `excluded_exceptions`. Unlike `exclude`,
   `excluded_exceptions` will also exclude all subclasses. 
- The `send_callback` option has been renamed to `should_capture`.
- The `name` option has been renamed to `server_name`.
- The `project` option has been removed.
- The `extra_data` option has been removed in favour of setting additional data
  directly in the context.
- The `curl_method` option has been removed in favour of leaving to the user the
  choice of setting an HTTP client supporting syncronous, asyncronous or both
  transport methods.
- The `curl_path` option has been removed.
- The `curl_ipv4` option has been removed.
- The `curl_ssl_version` option has been removed.
- The `verify_ssl` option has been removed.
- The `ca_cert` option has been removed.
- The `proxy` option has been removed in favour of leaving to the user the burden
  of configuring the HTTP client options using a custom client.
- The `processors` option has been removed in favour of leaving to the user the
  choice of which processors add or remove using the appropriate methods of the
  `Client` and `ClientBuilder` classes.
- The `processors_options` option has been removed in favour of leaving to the
  user the burden of adding an already configured processor instance to the client.
- The `transport` option has been removed in favour of setting it using the
  client builder.
- The `install_default_breadcrumb_handlers` option has been removed.
- The `http_client_options` has been added to set the options that applies to the
  HTTP client chosen by the user as underlying transport method.
- The `open_timeout` option has been added to set the maximum number of seconds
  to wait for the server connection to open.
- The `excluded_loggers` option has been added to set the list of logger 'progname's
  to exclude from breadcrumbs.
- The `environments` option has been added to set the whitelist of environments
  that will send notifications to Sentry.
- The `serialize_all_object` option has been added to configure whether all the
  object instances should be serialized.
- The `context_lines` option has been added to configure the number of lines of
  code context to capture.
- The `server` option has been renamed to `dsn`.

### Client

- The `Raven_Client` class has been renamed to `Client` to follow the PSR-4
  convention.

- The constructor of the `Raven_Client` class has changed its signature. The only way
  to set the DSN is now to set it in the options passed to the `Configuration`
  class.

  Before:

  ```php
  public function __construct($options_or_dsn = null, $options = array())
  {
      // ...
  }
  ```

  After:

  ```php
  public function __construct(Configuration $config, HttpAsyncClient $httpClient, RequestFactory $requestFactory)
  {
      // ...
  }
  ```

- The methods `Raven_Client::getRelease` and `Raven_Client::setRelease` have
  been removed. You should use `Configuration::getRelease()` and `Configuration::setRelease`
  instead.

  Before:

  ```php
  $client->getRelease();
  $client->setRelease(...);
  ```

  After:

  ```php
  $client->getConfig()->getRelease();
  $client->getConfig()->setRelease(...);
  ```

- The methods `Raven_Client::getEnvironment` and `Raven_Client::setEnvironment`
  have been removed. You should use `Configuration::getCurrentEnvironment` and
  `Configuration::setCurrentEnvironment` instead.

  Before:

  ```php
  $client->getEnvironment();
  $client->setEnvironment(...);
  ```

  After:

  ```php
  $client->getConfig()->getCurrentEnvironment();
  $client->getConfig()->setCurrentEnvironment(...);
  ```

- The methods `Raven_Client::getDefaultPrefixes` and `Raven_Client::setPrefixes`
  have been removed. You should use `Configuration::getPrefixes` and
  `Configuration::setPrefixes` instead.

  Before:

  ```php
  $client->getPrefixes();
  $client->setPrefixes(...);
  ```

  After:

  ```php
  $client->getConfig()->getPrefixes();
  $client->getConfig()->setPrefixes(...);
  ```

- The methods `Raven_Client::getAppPath` and `Raven_Client::setAppPath` have been
  removed. You should use `Configuration::getProjectRoot` and `Configuration::setProjectRoot`
  instead.

  Before:

  ```php
  $client->getAppPath();
  $client->setAppPath(...);
  ```

  After:

  ```php
  $client->getConfig()->getProjectRoot();
  $client->getConfig()->setProjectRoot(...);

- The methods `Raven_Client::getExcludedAppPaths` and `Raven_Client::setExcludedAppPaths`
  have been removed. You should use `Configuration::getExcludedProjectPaths`
  and `Configuration::setExcludedProjectPaths` instead.

  Before:

  ```php
  $client->getExcludedAppPaths();
  $client->setExcludedAppPaths(...);
  ```

  After:

  ```php
  $client->getConfig()->getExcludedProjectPaths();
  $client->getConfig()->setExcludedProjectPaths(...);

- The methods `Raven_Client::getSendCallback` and `Raven_Client::setSendCallback` have been
  removed. You should use `Configuration::shouldCapture` and `Configuration::setShouldCapture`
  instead.

  Before:

  ```php
  $client->getSendCallback();
  $client->setSendCallback(...);
  ```

  After:

  ```php
  $client->getConfig()->shouldCapture();
  $client->getConfig()->setShouldCapture(...);

- The method `Raven_Client::getServerEndpoint` has been removed. You should use
  `Configuration::getDsn` instead.

  Before:

  ```php
  $client->getServerEndpoint();
  ```

  After:

  ```php
  $client->getConfig()->getServer();
  ```

- The method `Raven_Client::getTransport` has been removed. You should use
  `Configuration::getTransport` instead.

  Before:

  ```php
  $client->getTransport();
  ```

  After:

  ```php
  $client->getConfig()->getTransport();
  ```

- The method `Raven_Client::getErrorTypes` has been removed. You should use
  `Configuration::getErrorTypes` instead.

  Before:

  ```php
  $client->getErrorTypes();
  ```

  After:

  ```php
  $client->getConfig()->getErrorTypes();
  ```

- The `Raven_Client::getDefaultProcessors` method has been removed.

- The `Raven_Client::message` method has been removed.

- The `Raven_Client::captureQuery` method has been removed.

- The `Raven_Client::captureMessage` method has changed its signature by removing the
  `$stack` and `$vars` arguments.

  Before:

  ```php
  public function captureMessage($message, $params = array(), $data = array(), $stack = false, $vars = null)
  {
      // ...
  }
  ```

  After:

  ```php
  public function captureMessage($message, array $params = [], array $payload = [])
  {
      // ...
  }
  ```

- The `Raven_Client::captureException` method has changed its signature by removing
  the `$logger` and `$vars` arguments.

  Before:

  ```php
  public function captureException($exception, $data = null, $logger = null, $vars = null)
  {
      // ...
  }
  ```

  After:

  ```php
  public function captureException($exception, array $payload = [])
  {
      // ...
  }
  ```

- The `$vars` argument of the `Raven_Client::captureException`, `Raven_Client::captureMessage`
  and `Raven_Client::captureQuery` methods accepted some values that were
  setting additional data in the event like the tags or the user data.
  Some of them have changed name.

  Before:

  ```php
  $vars = array(
      'tags' => array(...),
      'extra' => array(...),
      'user' => array(...),
  );

  $client->captureException(new Exception(), null, null, $vars);
  ```

  After:

  ```php
  $payload = array(
      'tags_context' => array(...),
      'extra_context' => array(...),
      'user_context' => array(...),
  );

  $client->captureException(new Exception(), $payload);
  ```

- If an exception implemented the `getSeverity()` method its value was used as error
  level of the event. This has been changed so that only the `ErrorException` or its
  derivated classes are considered for this behavior.

- The method `Raven_Client::createProcessors` has been removed as there is no
  need to create instances of the processors from outside the `Client` class.

- The method `Raven_Client::setProcessors` has been removed. You should use
  `Client::addProcessor` and `Client::removeProcessor` instead to manage the
  processors that will be executed.

  Before:

  ```php
  $processor1 = new Processor();
  $processor2 = new Processor();

  $client->setProcessors(array($processor2, $processor1));
  ```

  After:

  ```php
  $processor1 = new Processor();
  $processor2 = new Processor();

  $client->addProcessor($processor2);
  $client->addProcessor($processor1);

  // or

  $client->addProcessor($processor1);
  $client->addProcessor($processor2, 255); // Note the priority: higher the priority earlier a processor will be executed. This is equivalent to adding first $processor2 and then $processor1
  ```

- The method `Raven_Client::process` has been removed as there is no need to process event data
  from outside the `Client` class.

- The method `Raven_Client::sanitize` has been removed and the sanitization
  happens now inside the `SanitizerMiddleware` middleware.

- The `Raven_Client::user_context` method has been removed. You should use
  `Client::getUserContext` instead.

  Before:

  ```php
  $client->user_context(array('foo' => 'bar'));
  ```

  After:

  ```php
  $client->getUserContext()->setData(array('foo' => 'bar'));
  ```

- The `Raven_Client::tags_context` method has been removed. You should use
  `Client::getTagsContext` instead.

  Before:

  ```php
  $client->tags_context(array('foo', 'bar'));
  ```

  After:

  ```php
  $client->getTagsContext()->setData(array('foo', 'bar'));
  ```

- The `Raven_Client::extra_context` method has been removed. You should use
  `Client::getExtraContext` instead.

  Before:

  ```php
  $client->extra_context(array('foo' => 'bar'));
  ```

  After:

  ```php
  $client->getExtraContext()->setData(array('foo' => 'bar'));
  ```

- The `transaction` property has been made private. You should use `Client::getTransactionStack`
  instead to access the instance of the object.

  Before:

  ```php
  $client->transaction->push('foo');
  ```

  After:

  ```php
  $client->getTransactionStack()->push('foo');
  ```

- The method `Raven_Client::install` has been removed. You should use `ErrorHandler::register`
  instead to register an error handler and associate it with the client.

  Before:

  ```php
  $client->install();
  ```

  After:

  ```php
  use Sentry\ErrorHandler;

  ErrorHandler::register($client);
  ```

- The method `Raven_Client::registerDefaultBreadcrumbHandlers` has been removed.
  You should use `BreadcrumbErrorHandler::register` instead to register an error
  handler and associate it with the client.

  Before:

  ```php
  $client = new Raven_Client(array('install_default_breadcrumb_handlers' => true));
  ```

  After:

  ```php
  use Sentry\BreadcrumbErrorHandler;

  $client = new Client([...]);

  BreadcrumbErrorHandler::register($client);
  ```

### Client builder

- To simplify the creation of a `Client` object instance, a new builder class
  has been added.

  Before:

  ```php
  $client = new Raven_Client([...]);
  ```

  After:

  ```php
  $httpClient = new HttpClient(); // This can be any Httplug client adapter
  $requestFactory = new RequestFactory(); // This can be any Httplug PSR-7 request factory
  $client = new Client(new Configuration([...], $httpClient, $requestFactory));

  // or

  $client = ClientBuilder::create([...])->getClient();
  ```

### Processors

All processor have been refactored into middlewares, and have changed names
to follow PSR-4 convention.

- The `Raven_Processor_RemoveCookiesProcessor` class has been renamed to 
  `SanitizeCookiesMiddleware` to better reflect its purpose. The constructor 
  accepts an array of options to make the behaviour of which cookies to 
  sanitize configurable.

- The `Raven_Processor_SanitizeStacktraceProcessor` class has been renamed to
  `RemoveStacktraceContextMiddleware` to follow the PSR-4 convention and better
  reflect its purpose.

- The `Raven_Processor_SanitizeHttpHeadersProcessor` class has been renamed to
  `SanitizeHttpHeadersMiddleware` to follow the PSR-4 convention.

- The `Raven_Processor_RemoveHttpBodyProcessor` class has been renamed to
  `RemoveHttpBodyMiddleware` to follow the PSR-4 convention.

- The `Raven_Processor_SanitizeDataProcessor` class has been renamed to
  `SanitizeDataMiddleware` to follow the PSR-4 convention.

- The `Raven_Processor` class has been removed. There is not anymore a base
  abstract class for the processors, but a `ProcessorMiddlewareInterface` interface has
  been introduced.

### Context

- The `Raven_Context` class has been renamed to `Context` and added to the `Raven`
  namespace to follow the PSR-4 convention.

- The `tags`, `extra` and `user` properties of the `Raven_Context` class have
  been removed. Each instance of the new class represents now a single context
  type only and provides some useful methods to interact with the data.

### Transactions

- The method `TransactionStack::push` has changed its signature. It used to accept
  a single value only, but now more values can be passed in a single call.

  Before:

  ```php
  $client->transaction->push('foo');
  $client->transaction->push('bar');
  ```

  After:

  ```php
  $client->getTransactionStack()->push('foo');
  $client->getTransactionStack()->push('bar');

  // or

  $client->getTransactionStack()->push('foo', 'bar');
  ```

- The method `TransactionStack::pop` has changed its signature by removing the
  `$context` argument. Consequently the behaviour of the method in regards to
  the returned value changed as well: it's not possible anymore to pop all values
  up to one that equals the value of `$context`.

  Before:

  ```php
  $client->transaction->push('foo', 'bar', 'baz');

  $value = $client->transaction->pop(); // $value is 'baz'
  $value = $client->transaction->pop('foo'); // $value is 'foo'
  $value = $client->transaction->pop(); // $value is null
  ```

  After:

  ```php
  $client->getTransactionStack()->push('foo', 'bar', 'baz');

  while (!$client->getTransactionStack()->isEmpty()) {
      $value = $client->getTransactionStack()->pop(); // $value is 'baz', then 'bar', then 'foo'
  }

  $value = $client->getTransactionStack()->pop(); // $value is null
  ```

## Error handlers

- The `Raven_Breadcrumbs_ErrorHandler` class has been renamed to `BreadcrumbErrorHandler`
  to better reflect its purpose and to follow the PSR-4 convention.

- The constructor of the `Raven_Breadcrumbs_ErrorHandler` class has changed its
  visibility to `protected`. To create a new instance of the class use the new
  method `BreadcrumbErrorHandler::register`.

- The method `Raven_Breadcrumbs_ErrorHandler::install` has been renamed to
  `register` and changed its signature to accept the instance of the Raven
  client to associate with the handler itself.

  Before:

  ```php
  $errorHandler = new Raven_Breadcrumbs_ErrorHandler($client);
  $errorHandler->install();
  ```

  After:

  ```php
  use Sentry\BreadcrumbErrorHandler;

  $errorHandler = BreadcrumbErrorHandler::register($client);
  ```

- The `Raven_ErrorHandler` class has been renamed to `ErrorHandler` to follow
  the PSR-4 convention.

- The constructor of the `Raven_ErrorHandler` class has changed its visibility
  to `protected`. To create a new instance of the class use the new method
  `ErrorHandler::register`.

  Before:

  ```php
  $errorHandler = new Raven_ErrorHandler($client);
  ```

  After:

  ```php
  use Sentry\ErrorHandler;

  $errorHandler = ErrorHandler::register($client);
  ```

- The method `Raven_ErrorHandler::handleError` has changed its signature by removing
  the `$context` argument and it has been marked as internal to make it clear that
  it should not be called publicly and its method visibility is subject to changes
  without any notice.

- The methods `Raven_ErrorHandler::registerErrorHandler`, `Raven_ErrorHandler::registerExceptionHandler`
  and `Raven_ErrorHandler::registerShutdownFunction` have been removed. You should
  use the `ErrorHandler::register` method instead, but note that it registers all
  error handlers (error, exception and fatal error) at once and there is no way
  anymore to only use one of them.

  Before:

  ```php
  $errorHandler = new Raven_ErrorHandler($client);
  $errorHandler->registerErrorHandler();
  $errorHandler->registerExceptionHandler();
  $errorHandler->registerShutdownFunction();
  ```

  After:

  ```php
  use Sentry\ErrorHandler;

  $errorHandler = ErrorHandler::register($client);
  ```

- The method `Raven_ErrorHandler::handleError` has changed its signature by
  removing the `$context` argument and it has been marked as internal to
  make it clear that it should not be called publicly and its method visibility
  is subject to changes without any notice.

- The method `Raven_ErrorHandler::handleFatalError` has changed its signature
  by adding an optional argument named `$error` and it has been marked as internal
  to make it clear that it should not be called publicly and its method visibility
  is subject to changes without any notice.

- The method `Raven_ErrorHandler::handleException` has changed its signature by
  removing the `$isError` and `$vars` arguments and it has been marked as internal
  to make it clear that it should not be called publicly and its method visibility
  is subject to changes without any notice.

- The method `Raven_ErrorHandler::bitwiseOr` has been removed and there is no
  replacement for it.

- The method `Raven_ErrorHandler::shouldCaptureFatalError` has been removed and
  there is no replacement for it.
