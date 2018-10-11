Usage
=====

Using Sentry with PHP is straightforward.  After installation of the library
you can directly interface with the client and start submitting data.

Basics
------

The most important part is the creation of the raven client.  Create it
once and reference it from anywhere you want to interface with Sentry:

.. code-block:: php

    $sentryClient = new Raven_Client('___PUBLIC_DSN___');


Capturing Errors
----------------

Sentry includes basic functionality for reporting any uncaught
exceptions or PHP errors. This is done via the error handler,
and appropriate hooks for each of PHP's built-in reporting:

.. code-block:: php

    $error_handler = new Raven_ErrorHandler($sentryClient);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();

.. note:: Calling ``install()`` on a Raven_Client instance will automatically
          register these handlers.


Reporting Exceptions
--------------------

If you want to report exceptions manually you can use the
`captureException` function.

.. code-block:: php

    // Basic Reporting
    $sentryClient->captureException($ex);

    // Provide some additional data with an exception
    $sentryClient->captureException($ex, array(
        'extra' => array(
            'php_version' => phpversion()
        ),
    ));


Reporting Other Errors
----------------------

Sometimes you don't have an actual exception object, but something bad happened and you
want to report it anyways.  This is where `captureMessage` comes in.  It
takes a message and reports it to sentry.

.. code-block:: php

    // Capture a message
    $sentryClient->captureMessage('my log message');

Note, ``captureMessage`` has a slightly different API than ``captureException`` to support
parameterized formatting:

.. code-block:: php

    $sentryClient->captureMessage('my %s message', array('log'), array(
        'extra' => array(
            'foo' => 'bar',
        ),
    ));


Optional Attributes
-------------------

With calls to ``captureException`` or ``captureMessage`` additional data
can be supplied:

.. code-block:: php

    $sentryClient->captureException($ex, array(
        'attr' => 'value',
    ));


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

    $sentryClient->getLastEventID();

.. _php-user-feedback:

User Feedback
-------------

To enable user feedback for crash reports you will need to create an error handler
which is aware of the last event ID.

.. sourcecode:: php

    <?php

    $sentry = new \Raven_Client(___PUBLIC_DSN___);

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

    if ($sentryClient->getLastError() !== null) {
        echo "Something went very, very wrong";
        // $sentryClient->getLastError() contains the error that occurred
    } else {
        // Give the user feedback
        echo "Sorry, there was an error!";
        echo "Your reference ID is " . $event_id;
    }


Breadcrumbs
-----------

Sentry supports capturing breadcrumbs -- events that happened prior to an issue.

.. code-block:: php

    $sentryClient->breadcrumbs->record(array(
        'message' => 'Authenticating user as ' . $username,
        'category' => 'auth',
        'level' => 'info',
    ));


Filtering Out Errors
--------------------

Its common that you might want to prevent automatic capture of certain areas. Ideally you simply would avoid calling out to Sentry in that case, but that's often easier said than done. Instead, you can provide a function which the SDK will call before it sends any data, allowing you both to mutate that data, as well as prevent it from being sent to the server.

.. code-block:: php

    $sentryClient->setSendCallback(function($data) {
        $ignore_types = array('Symfony\Component\HttpKernel\Exception\NotFoundHttpException');

        if (isset($data['exception']) && in_array($data['exception']['values'][0]['type'], $ignore_types))
        {
            return false;
        }
    });


Error Control Operators
-----------------------

In PHP its fairly common to use the `suppression operator <http://php.net/manual/en/language.operators.errorcontrol.php>`_
to avoid bubbling up handled errors:

.. code-block:: php

    $my_file = @file('non_existent_file');

In these situations, Sentry will never capture the error. If you wish to capture it at that stage
you'd need to manually call out to the PHP client:

.. code-block:: php

    $my_file = @file('non_existent_file');
    if (!$my_file) {
        // ...
        $sentryClient->captureLastError();
    }


Testing Your Connection
-----------------------

The PHP client includes a simple helper script to test your connection and
credentials with the Sentry master server::

    $ bin/sentry test ___PUBLIC_DSN___
    Client configuration:
    -> server: [___API_URL___]
    -> project: ___PROJECT_ID___
    -> public_key: ___PUBLIC_KEY___

    Sending a test event:
    -> event ID: f1765c9aed4f4ceebe5a93df9eb2d34f

    Done!
