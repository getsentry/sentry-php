Middlewares
###########

Middlewares are an essential part of the event sending lifecycle. Each captured
event is passed through the middleware chain before being sent to the server and
each middleware can edit the data to add, change or remove information. There are
several built-in middlewares whose list is:

- ``BreadcrumbInterfaceMiddleware``: adds all the recorded breadcrumbs up to the
  point the event was generated.
- ``ContextInterfaceMiddleware``: adds the data stored in the contexts to the
  event.
- ``ExceptionInterfaceMiddleware``: fetches the stacktrace for the captured event
  if it's an exception (and has an stacktrace) and integrates additional data like
  a small piece of source code for each stackframe.
- ``MessageInterfaceMiddleware``: adds a message (if present) to the event
  and optionally format it using ``vsprintf``.
- ``ModulesMiddleware``: fetches informations about the packages installed through
  Composer. The ``composer.lock`` file must be present for this middleware to work.
- ``ProcessorMiddleware``: executes the registered processors by passing to them
  the event instance.
- ``RequestInterfaceMiddleware``: adds the HTTP request information (e.g. the
  headers or the query string) to the event.
- ``SanitizerMiddleware``: sanitizes the data of the event to ensure that it
  can be encoded correctly as JSON and the data is serialized in the appropriate
  format for their representation.
- ``UserInterfaceMiddleware``: adds some user-related information like the client
  IP address to the event.

There are also some "special" middlewares that should be executed after all the
other middlewares so that they can sanitize and remove sensitive information before
they reach the Sentry server:

- ``RemoveHttpBodyMiddleware``: sanitizes the data sent as body of a POST
  request.
- ``SanitizeCookiesMiddleware``: sanitizes the cookies sent with the request
  by hiding sensitive information.
- ``SanitizeDataMiddleware``: sanitizes the data of the event by removing
  sensitive information.
- ``SanitizeHttpHeadersMiddleware``: sanitizes the headers of the request by
  hiding sensitive information.
- ``RemoveStacktraceContextMiddleware``: sanitizes the captured stacktrace by
  removing the excerpts of source code attached to each frame. This middleware
  is not enabled by default.

Writing a middleware
====================

The only requirement for a middleware is that it must be a callable. What this
means is that you can register an anonymous function as middleware as well as
create a class with the magic method ``__invoke`` and they will both work fine.
The signature of the function that will be called must be the following:

.. code-block:: php

  function (\Sentry\Event $event, callable $next, \Psr\Http\Message\ServerRequestInterface $request = null, $exception = null, array $payload = [])

The middleware can call the next one in the chain or can directly return the
event instance and break the chain. Additional data supplied by the user while
calling the ``capture*`` methods of the ``Client`` class will be passed to each
middleware in the ``$payload`` argument. The example below shows how a simple
middleware that customizes the message captured with an event can be written:

.. code-block:: php

  use Psr\Http\Message\ServerRequestInterface;
  use Sentry\ClientBuilder;
  use Sentry\Event;

  final class CustomMiddleware
  {
      public function __invoke(Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = [])
      {
          $event->setMessage('hello world');

          return $next($event, $request, $exception, $payload);
      }
  }

Using a middleware
==================

The middleware needs to be added to the stack before it can be used. Each one
can have a priority which defines in which order they will run. If you don't
specify a priority the default one of 0 will be assigned. The built-in middlewares
have the following priorities:

- ``SanitizerMiddleware``: -255 (this middleware should always be the last one)
- ``SanitizeDataMiddleware``: -200 (this middleware should always be after
  all "standard" middlewares)
- ``SanitizeCookiesMiddleware``: -200 (this middleware should always be after
  all "standard" middlewares)
- ``RemoveHttpBodyMiddleware``: -200 (this middleware should always be after
  all "standard" middlewares)
- ``SanitizeHttpHeadersMiddleware``: -200 (this middleware should always be after
  all "standard" middlewares)
- ``RemoveStacktraceContextMiddleware``: -200 (this middleware should always be after
  all "standard" middlewares)
- ``MessageInterfaceMiddleware``: 0
- ``RequestInterfaceMiddleware``: 0
- ``UserInterfaceMiddleware``: 0
- ``ContextInterfaceMiddleware``: 0 (this middleware fills the information about
  the tags context)
- ``ContextInterfaceMiddleware``: 0 (this middleware fills the information about
  the user context)
- ``ContextInterfaceMiddleware``: 0 (this middleware fills the information about
  the extra context)
- ``ContextInterfaceMiddleware``: 0 (this middleware fills the information about
  the runtime context)
- ``ContextInterfaceMiddleware``: 0 (this middleware fills the information about
  the server OS context)
- ``BreadcrumbInterfaceMiddleware``: 0
- ``ExceptionInterfaceMiddleware``: 0
- ``ModulesMiddleware``: 0 (this middleware is not enabled by default)

The higher the priority value is, the earlier a middleware will be executed in
the chain. To add the middleware to the stack you can use the ``addMiddleware``
method which can be found in both the ``Client`` and ``ClientBuilder`` classes.
To remove a middleware you can use the ``removeMiddleware`` method instead. You
can manage the middlewares at runtime and the chain will be recomputed accordingly.

.. code-block:: php

  use Psr\Http\Message\ServerRequestInterface;
  use Sentry\ClientBuilder;
  use Sentry\Event;

  $middleware = function (Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = []) {
      // Do something here

      return $next($event, $request, $exception, $payload);
  };

  $clientBuiler = new ClientBuilder();
  $clientBuilder->addMiddleware($middleware, 10);
  $clientBuilder->removeMiddleware($middleware);

  $client = $clientBuilder->getClient();
  $client->addMiddleware($middleware, -10);
  $client->removeMiddleware($middleware);
