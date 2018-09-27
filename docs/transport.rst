Sending events
##############

Sending an event is very straightforward: you create an instance of the client
by configuring it and passing to it a transport and then you can use it to send
the event.

Transport types
===============

Transports are the classes in Sentry PHP that are responsible for communicating
with a service in order to deliver an event. There are several types of transports
available out-of-the-box, all of which implement the ``TransportInterface``
interface;

- The ``NullTransport`` which is used in case no server where errors should be
  sent is set in the client configuration.
- The ``HttpTransport`` which is the default and will be used when the server
  is set in the client configuration.
- The ``SpoolTransport`` which can be used to defer the sending of events (e.g.
  by putting them into a queue).

The null transport
==================

Although not so common there could be cases in which you don't want to send
events at all. The ``NullTransport`` transport does this: it simply ignores
the events, but report them as sent.

.. code-block:: php

  use Sentry\Client;
  use Sentry\Configuration;
  use Sentry\Transport\NullTransport;

  // Even though the server is configured for the client, using the NullTransport
  // transport won't send any event

  $configuration = new Configuration(['server' => 'http://public:secret@example.com/1']);
  $client = new Client($configuration, new NullTransport());

The HTTP transport
==================

The ``HttpTransport`` sends events over the HTTP protocol using Httplug_: the
best adapter available is automatically selected when creating a client instance
through the client builder, but you can override it using the appropriate methods.

.. code-block:: php

  use Sentry\Client;
  use Sentry\Configuration;
  use Sentry\Transport\HttpTransport;

  $configuration = new Configuration(['server' => 'http://public:secret@example.com/1']);
  $transport = new HttpTransport($configuration, HttpAsyncClientDiscovery::find(), MessageFactoryDiscovery::find());
  $client = new Client($configuration, $transport);

The spool transport
===================

The default behavior is to send events immediatly. You may, however, want to
avoid waiting for the communication to the Sentry server that could be slow
or unreliable. This can be avoided by choosing the ``SpoolTransport`` which
stores the events in a queue so that another process can read it and and take
care of sending them. Currently only spooling to memory is supported.

.. code-block:: php

  use Sentry\Client;
  use Sentry\Configuration;
  use Sentry\Transport\SpoolTransport;

  // The transport used by the client to send the events uses the memory spool
  // which stores the events in a queue in-memory

  $spool = new MemorySpool();
  $transport = new NullTransport();
  $client = new Client(new Configuration(), new SpoolTransport($spool));

  // When the spool queue is flushed the events are sent using the transport
  // passed as parameter of the flushQueue method.

  $spool->flushQueue($transport);

.. _Httplug: http://httplug.io/
