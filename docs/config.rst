Configuration
=============

Several options exist that allow you to configure the behavior of the
``Raven_Client``. These are passed as the second parameter of the
constructor, and is expected to be an array of key value pairs:

.. code-block:: php

    $client = new Raven_Client($dsn, array(
        'option_name' => 'value',
    ));

Available Settings
------------------

The following settings are available for the client:

.. describe:: name

    A string to override the default value for the server's hostname.

    Defaults to ``Raven_Compat::gethostname()``.

.. describe:: tags

    An array of tags to apply to events in this context.

    .. code-block:: php

        'tags' => array(
            'php_version' => phpversion(),
        )


.. describe:: curl_method

    Defaults to 'sync'.

    Available methods:

    - ``sync`` (default): send requests immediately when they're made
    - ``async``: uses a curl_multi handler for best-effort asynchronous
      submissions
    - ``exec``: asynchronously send events by forking a curl
      process for each item

.. describe:: curl_path

    Defaults to 'curl'.

    Specify the path to the curl binary to be used with the 'exec' curl
    method.

.. describe:: trace

    Set this to ``false`` to disable reflection tracing (function calling
    arguments) in stacktraces.


.. describe:: logger

    Adjust the default logger name for messages.

    Defaults to ``php``.

.. describe:: ca_cert

    The path to the CA certificate bundle.

    Defaults to the common bundle which includes getsentry.com:
    ./data/cacert.pem

    Caveats:

    - The CA bundle is ignored unless curl throws an error suggesting it
      needs a cert.
    - The option is only currently used within the synchronous curl
      transport.

.. describe:: curl_ssl_version

    The SSL version (2 or 3) to use.  By default PHP will try to determine
    this itself, although in some cases this must be set manually.

.. describe:: message_limit

    Defaults to 1024 characters.

    This value is used to truncate message and frame variables. However it
    is not guarantee that length of whole message will be restricted by
    this value.

.. describe:: processors

    An array of classes to use to process data before it is sent to
    Sentry. By default, ``Raven_SanitizeDataProcessor`` is used

.. describe:: processorOptions

    Options that will be passed on to a ``setProcessorOptions()`` function
    in a ``Raven_Processor`` sub-class before that Processor is added to
    the list of processors used by ``Raven_Client``

    An example of overriding the regular expressions in
    ``Raven_SanitizeDataProcessor`` is below:

    .. code-block:: php

        'processorOptions' => array(
            'Raven_SanitizeDataProcessor' => array(
                        'fields_re' => '/(user_password|user_token|user_secret)/i',
                        'values_re' => '/^(?:\d[ -]*?){15,16}$/'
                    )
        )

.. _raven-php-request-context:

Providing Request Context
-------------------------

Most of the time you're not actually calling out to Raven directly, but
you still want to provide some additional context. This lifecycle
generally constists of something like the following:

- Set some context via a middleware (e.g. the logged in user)
- Send all given context with any events during the request lifecycle
- Cleanup context

There are three primary methods for providing request context:

.. code-block:: php

    // bind the logged in user
    $client->user_context(array('email' => 'foo@example.com'));

    // tag the request with something interesting
    $client->tags_context(array('interesting' => 'yes'));

    // provide a bit of additional context
    $client->extra_context(array('happiness' => 'very'));


If you're performing additional requests during the lifecycle, you'll also
need to ensure you cleanup the context (to reset its state):

.. code-block:: php

    $client->context->clear();
