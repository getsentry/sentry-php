Quickstart
##########

Using Sentry with PHP is straightforward. After installation of the library you
can directly interface with the client and start submitting data.

Creating a Client
=================

The most important part is the creation of the client instance. To ease the
instantiation of the object a builder class is provided which permits an easy
configuration of the options and the features of the client. Using it is the
recommended approach as it hides the complexity of directly using the constructor
of the ``Client`` class which needs two arguments:

``$config``
    (``Configuration``) Storage for the client configuration. While the DSN is
    immutable after it has been set, almost all other options can be changed at
    any time while the client is already in use.

``$transport``
    (``TransportInterface``) Class that is responsible for sending the events
    over the wire.

When using the client builder both these arguments are filled when getting the
instance of the client. The configuration options can be set by calling on the
client builder instance the same methods available in the ``Configuration``
class. The transport, the middlewares and the processors can either be managed
before the client instance is initialized.

.. code-block:: php

  use Raven\ClientBuilder;

  $client = ClientBuilder::create(['server' => 'http://public:secret@example.com/1'])->getClient();

Sending errors
==============

The client provides some methods to send both the last thrown error or a catched
exception:

.. code-block:: php

  $client->captureLastError();
  $client->captureException(new \Exception('foo'));

Sending messages
================

You may want to report messages instead of errors. To do it you can use the
``captureMessage`` method, which accept a string representing the message and
an optional list of parameters that will be substituted in it by the ``vsprintf``
function before sending the event.

.. code-block:: php

  // Both lines will report the same message
  $client->captureMessage('foo bar');
  $client->captureMessage('foo %s', ['bar']);

Sending other data
==================

Sometimes you want to report an event in which you want to fill data yourself.
This is where the generic ``capture`` method comes in: it takes a payload of
data and creates an event from it.

.. code-block:: php

  $client->capture(['level' => 'debug', 'message' => 'foo bar']);

Sending additional data
=======================

You may want to send additional data with an event that has been captured by
one of the ``capture*`` methods along the lines of what you can do in the
previous doc section. A payload of data can always be specified as last argument
of the methods discussed above and according to the key you pass you can set
additional data or override some preset information.

.. code-block:: php

  // The error level of the captured error will be overwritten
  $client->captureLastError(['level' => 'warning']);

  // An additional parametrized message will be sent with the captured exception
  $client->captureException(new \RuntimeException('foo bar'), ['message' => 'bar %s', 'message_params' => ['baz']]);

  // The logger will be overwritten
  $client->captureMessage('foo', [], ['logger' => 'custom']);

Getting back an event ID
========================

An event ID is a UUID4 globally uniqued ID that is generated for the event and
that you can use to find the exact event from within Sentry. It is returned from
any ``capture*`` method. There is also a function called ``getLastEvent`` which
you can use to retrieve the lsat captured event and from there get its own ID.

.. code-block:: php

  // Both the following lines will return the same ID, but it's recommended to always get it from the capture method
  $eventId = $client->captureLastError();
  $eventId = $client->getLastEvent()->getId();

Capturing breadcrumbs manually
==============================

Even though breadcrumbs can be captured automatically when an error or exception
occurs, you may want to report them manually too. The client gives access to some
methods to report and clear the breadcrumbs.

.. code-block:: php

  use Raven\Breadcrumbs\Breadcrumb;

  $client->leaveBreadcrumb(Breadcrumb::create('debug', 'error', 'error_reporting', 'foo bar'));
  $client->clearBreadcrumbs();

The default implementation of the breadcrumbs recorder is a circular buffer, so
when you reach out the maximum number of items that it can store at the same time
(100 by default) the oldest items will be replaced with the newest ones.
