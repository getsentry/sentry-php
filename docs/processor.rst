Processors
##########

The processors are classes that are executed as the last step of the event
sending lifecycle before the event data is serialized and sent using the
configured transport. There are several built-in processors (not all are
enabled by default) whose list is:

- ``RemoveHttpBodyProcessor``: sanitizes the data sent as body of a POST
  request.
- ``SanitizeCookiesProcessor``: sanitizes the cookies sent with the request
  by hiding sensitive information.
- ``SanitizeDataProcessor``: sanitizes the data of the event by removing
  sensitive information.
- ``SanitizeHttpHeadersProcessor``: sanitizes the headers of the request by
  hiding sensitive information.
- ``SanitizeStacktraceProcessor``: sanitizes the captured stacktrace by
  removing the excerpts of source code attached to each frame.

Writing a processor
===================

You can write your own processor by creating a class that implements the
``ProcessorInterface`` interface.

.. code-block:: php

  use Raven\Event;
  use Raven\Processor\ProcessorInterface;

  class MyCustomProcessor implements ProcessorInterface
  {
      public function process(Event $event)
      {
          // Do something on the event object instance

          return $event;
      }
  }

Using a processor
=================

The processors needs to be registered with the client instance before they are
used. Each one can have a priority which defines in which order they will run.
By default they have a priority of 0. The higher the priority value is, the
earlier a processor will be executed: this is similar to how the middlewares
work. You can add or remove the processors at runtime, and they will be executed
sequentially one after the other. The built-in processors have the following
priorities:

- ``SanitizeCookiesProcessor``: 0
- ``RemoveHttpBodyProcessor``: 0
- ``SanitizeHttpHeadersProcessor``: 0
- ``SanitizeDataProcessor``: -255

It's important to leave the ``SanitizeDataProcessor`` as the last processor so
that any sensitive information present in the event data will be sanitized
before being sent out from your network according to the configuration you
specified. To add a middleware to the client you can use the ``addProcessor``
method which can be found in both the ``Client`` and ``ClientBuilder`` classes
while the ``removeProcessor`` can be used to remove the processors instead.

.. code-block:: php

  use Raven\ClientBuilder;
  use Raven\Processor\RemoveHttpBodyProcessor;

  $processor = new RemoveHttpBodyProcessor();

  $clientBuilder = new ClientBuilder();
  $clientBuilder->addProcessor($processor, 10);
  $clientBuilder->removeProcessor($processor);

  $client = $clientBuilder->getClient();
  $client->addProcessor($processor, -10);
  $client->removeProcessor($processor);
