# CHANGELOG

## 4.0.1

The Sentry SDK team is happy to announce the immediate availability of Sentry PHP SDK v4.0.1.

### Bug Fixes

- Fix capturing out-of-memory errors when memory-constrained [(#1636)](https://github.com/getsentry/sentry-php/pull/1636)
- Check if the cURL extension is installed [(#1632)](https://github.com/getsentry/sentry-php/pull/1632)

## 4.0.0

The Sentry SDK team is thrilled to announce the immediate availability of Sentry PHP SDK v4.0.0.

# Breaking Change

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

# Features

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

# Misc

- The abandoned package `php-http/message-factory` was removed.
