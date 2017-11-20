# Upgrade from 1.7 to 2.0

### Client options

- The `environment` option has been renamed to `current_environment`.
- The `http_proxy` option has been renamed to `proxy`.
- The `processorOptions` option has been renamed to `processors_options`.
- The `exclude` option has been renamed to `excluded_exceptions`.
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

### Client

- The constructor of the `Client` class has changed its signature. The only way
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

- The methods `Client::getRelease` and `Client::setRelease` have been removed.
  You should use `Configuration::getRelease()` and `Configuration::setRelease`
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

- The methods `Client::getEnvironment` and `Client::setEnvironment` have been
  removed. You should use `Configuration::getCurrentEnvironment` and
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

- The methods `Client::getDefaultPrefixes` and `Client::setPrefixes` have been
  removed. You should use `Configuration::getPrefixes` and `Configuration::setPrefixes`
  instead.

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

- The methods `Client::getAppPath` and `Client::setAppPath` have been removed.
  You should use `Configuration::getProjectRoot` and `Configuration::setProjectRoot`
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

- The methods `Client::getExcludedAppPaths` and `Client::setExcludedAppPaths`
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

- The methods `Client::getSendCallback` and `Client::setSendCallback` have been
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

- The method `Client::getServerEndpoint` has been removed. You should use
  `Configuration::getServer` instead.

  Before:

  ```php
  $client->getServerEndpoint();
  ```

  After:

  ```php
  $client->getConfig()->getServer();
  ```

- The method `Client::getTransport` has been removed. You should use
  `Configuration::getTransport` instead.

  Before:

  ```php
  $client->getTransport();
  ```

  After:

  ```php
  $client->getConfig()->getTransport();
  ```

- The method `Client::getErrorTypes` has been removed. You should use
  `Configuration::getErrorTypes` instead.

  Before:

  ```php
  $client->getErrorTypes();
  ```

  After:

  ```php
  $client->getConfig()->getErrorTypes();
  ```

- The `Client::getDefaultProcessors` method has been removed.

- The `Client::message` method has been removed.

- The `Client::captureQuery` method has been removed.

- The `Client::captureMessage` method has changed its signature by removing the
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

- The `Client::captureException` method has changed its signature by removing the
  `$logger` and `$vars` arguments.

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

- The `$vars` argument of the `Client::captureException`, `Client::captureMessage` and
  `Client::captureQuery` methods accepted some values that were setting additional data
  in the event like the tags or the user data. Some of them have changed name.

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

### Client builder

- To simplify the creation of a `Client` object instance, a new builder class
  has been added.

  Before:

  ```php
  $client = new Client([...]);
  ```

  After:

  ```php
  $httpClient = new HttpClient(); // This can be any Httplug client adapter
  $requestFactory = new RequestFactory(); // This can be any Httplug PSR-7 request factory
  $client = new Client(new Configuration([...], $httpClient, $requestFactory));

  // or

  $client = ClientBuilder::create([...])->getClient();
  ```
