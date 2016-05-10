Usage
=====

Using Sentry with PHP is straightforward.  After installation of the library
you can directly interface with the client and start submitting data.

Basics
------

The most important part is the creation of the raven client.  Create it
once and reference it from anywhere you want to interface with Sentry:

.. code-block:: php

    $client = new Raven_Client('___DSN___');

Capturing Errors
----------------

The most basic functionality is to use Sentry for reporting any uncaught
exceptions or PHP errors.  As this functionality is common enough, Sentry
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

Optional Attributes
-------------------

With calls to ``captureException`` or ``captureMessage`` additional data
can be supplied:

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
        'fingerprint' => ['{{ default }}', 'other value']
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

Getting Back an Event ID
------------------------

An event id is a globally unique id for the event that was just sent. This
event id can be used to find the exact event from within Sentry.

This is often used to display for the user and report an error to customer
service.

.. code-block:: php

    $client->getLastEventID();

.. _php-user-feedback:

User Feedback
-------------

To enable user feedback for crash reports you will need to create an error handler
which is aware of the last event ID.

.. sourcecode:: php

    <?php

    $sentry = new \Raven_Client(___DSN___);

    public class App {
        function error500($exc) {
            $event_id = $sentry->captureException($exc);

            return $this->render('500.html', array(
                'sentry_event_id' => $event_id,
            ), 500);
        }
    }

Then in your template you can load up the feedback widget:

.. sourcecode:: html+django

    <!-- Sentry JS SDK 2.1.+ required -->
    <script src="https://cdn.ravenjs.com/2.3.0/raven.min.js"></script>

    {% if sentry_event_id %}
      <script>
      Raven.showReportDialog({
        eventId: '{{ sentry_event_id }}',

        // use the public DSN (dont include your secret!)
        dsn: '___PUBLIC_DSN___'
      });
      </script>
    {% endif %}

That's it!

For more details on this feature, see the :doc:`User Feedback guide <../../../learn/user-feedback>`.

Handling Failures
-----------------

The SDK attempts to minimize failures, and when they happen will always try to avoid bubbling them up
to your application. If you do want to know when an event fails to record, you can use the ``getLastError``
helper:

.. code-block:: php

    if ($client->getLastError() !== null) {
        echo "Something went very, very wrong";
        // $client->getLastError() contains the error that occurred
    } else {
        // Give the user feedback
        echo "Sorry, there was an error!";
        echo "Your reference ID is " . $event_id;
    }

Breadcrumbs
-----------

Sentry supports capturing breadcrumbs -- events that happened prior to an issue.

.. code-block:: php

    $client->breadcrumbs->record(array(
        'message' => 'Authenticating user as ' . $username,
        'category' => 'auth',
        'level' => 'info',
    ));

Testing Your Connection
-----------------------

The PHP client includes a simple helper script to test your connection and
credentials with the Sentry master server::

    $ bin/sentry test ___DSN___
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
