Usage
=====

Using Raven for PHP is straightforward.  After installation of the library
you can directly interface with the client and start submitting data.

Basics
------

The most important part is the creation of the raven client.  Create it
once and reference it from anywhere you want to interface with Sentry:

.. code-block:: php

    $client = new Raven_Client('___DSN___');

Capturing Errors
----------------

The most basic functionality is to use Raven for reporting any uncaught
exceptions or PHP errors.  As this functionality is common enough, Raven
provides support for this out of the box:

.. code-block:: php

    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();

Reporting Exceptions
--------------------

If you want to report exceptions manually you can use the
`captureException` function.

.. code-block:: php

    // Basic Reporting
    $event_id = $client->getIdent($client->captureException($ex));

    // Provide some additional data with an exception
    $event_id = $client->getIdent($client->captureException($ex, array(
        'extra' => array(
            'php_version' => phpversion()
        ),
    )));

Reporting Messages
------------------

Sometimes you don't have a PHP error but something bad happened and you
want to report it anyways.  This is where `captureMessage` comes in.  It
takes a message and reports it to sentry.

.. code-block:: php

    // Capture a message
    $event_id = $client->getIdent($client->captureMessage('my log message'));

Give User Feedback
------------------

The `event_id` returned can be shown to the user to help track down the
particular exception in Sentry later.  In case reporting to Sentry failed
you can also detect that:

.. code-block:: php

    if ($client->getLastError() !== null) {
        echo "Something went very, very wrong";
        // $client->getLastError() contains the error that occurred
    } else {
        // Give the user feedback
        echo "Sorry, there was an error!";
        echo "Your reference ID is " . $event_id;
    }

Optional Attributes
-------------------

With calls to ``captureException`` or ``captureMessage`` additional data
can be supplied::

  .. code-block:: php

      $client->captureException($ex, array('attr' => 'value'))

.. describe:: extra

    Additional context for this event. Must be a mapping. Children can be any native JSON type.

    .. code-block:: php

        array(
            'extra' => array('key' => 'value')
        )

.. describe:: fingerprint

    The fingerprint for grouping this event.

    .. code-block:: php

        array(
            // dont group events from the same NODE_ENV together
            'fingerprint' => ['{{ default }}', process.env.NODE_ENV]
        )

.. describe:: level

    The level of the event. Defaults to ``error``.

    .. code-block:: php

        array(
            'level' => 'warning'
        )

    Sentry is aware of the following levels:

    * debug (the least serious)
    * info
    * warning
    * error
    * fatal (the most serious)

.. describe:: logger

    The logger name for the event.

    .. code-block:: php

        array(
            'logger' => 'default'
        )

.. describe:: tags

    Tags to index with this event. Must be a mapping of strings.

    .. code-block:: php

        array(
            'tags' => array('key' => 'value')
        )

.. describe:: user

    The acting user.

    .. code-block:: php

        array(
            'user' => array(
                'id' => 42,
                'email' => 'clever-girl'
            )
        )

Testing Your Connection
-----------------------

The PHP client includes a simple helper script to test your connection and
credentials with the Sentry master server::

    $ bin/raven test ___DSN___
    Client configuration:
    -> server: [___API_URL___]
    -> project: ___PROJECT_ID___
    -> public_key: ___PUBLIC_KEY___
    -> secret_key: ___SECRET_KEY___

    Sending a test event:
    -> event ID: f1765c9aed4f4ceebe5a93df9eb2d34f

    Done!

.. note:: The CLI enforces the synchronous option on HTTP requests whereas
   the default configuration is asynchronous.
