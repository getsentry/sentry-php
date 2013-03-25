raven-php
=========

.. image:: https://secure.travis-ci.org/getsentry/raven-php.png?branch=master
   :target: http://travis-ci.org/getsentry/raven-php


raven-php is a PHP client for `Sentry <http://aboutsentry.com/>`_.

::

    // Instantiate a new client with a compatible DSN
    $client = new Raven_Client('http://public:secret@example.com/1');

    // Capture a message
    $event_id = $client->getIdent($client->captureMessage('my log message'));

    // Capture an exception
    $event_id = $client->getIdent($client->captureException($ex));

    // Provide some additional data with an exception
    $event_id = $client->getIdent($client->captureException($ex, array(
        'extra' => array(
            'php_version' => phpversion()
        ),
    )));

    // Give the user feedback
    echo "Sorry, there was an error!";
    echo "Your reference ID is " . $event_id;

    // Install error handlers and shutdown function to catch fatal errors
    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();

Installation
------------

Install with Composer
~~~~~~~~~~~~~~~~~~~~~

If you're using `Composer <https://github.com/composer/composer>`_ to manage
dependencies, you can add Raven with it.

::

    {
        "require": {
            "raven/raven": ">=0.2.0"
        }
    }

or to get the latest version off the master branch:

::

    {
        "require": {
            "raven/raven": "dev-master"
        }
    }


Install source from GitHub
~~~~~~~~~~~~~~~~~~~~~~~~~~

To install the source code:

::

    $ git clone git://github.com/getsentry/raven-php.git

And including it using the autoloader:

::

    require_once '/path/to/Raven/library/Raven/Autoloader.php';
    Raven_Autoloader::register();

Or, if you're using `Composer <https://github.com/composer/composer>`_:

::

    require_once 'vendor/autoload.php';
    
Configuration
-------------

Several options exist that allow you to configure the behavior of the ``Raven_Client``. These are passed as the
second parameter of the constructor, and is expected to be an array of key value pairs:

::

    $client = new Raven_Client($dsn, array(
        'option_name' => 'value',
    ));

``name``
~~~~~~~~

A string to override the default value for the server's hostname.

Defaults to ``Raven_Compat::gethostname()``.

``tags``
~~~~~~~~

An array of tags to apply to events in this context.

::

    'tags' => array(
        'php_version' => phpversion(),
    )

``signing``
~~~~~~~~~~~

Instructs the client to sign all messages. This behavior is deprecated in modern Sentry servers, and should
only be enabled if you need it for legacy compatibility.


``trace``
~~~~~~~~~

Set this to ``false`` to disable reflection tracing (function calling arguments) in stacktraces.


``logger``
~~~~~~~~~~

Adjust the default logger name for messages.

Defaults to ``php``.


Resources
---------

* `Bug Tracker <http://github.com/getsentry/raven-php/issues>`_
* `Code <http://github.com/getsentry/raven-php>`_
* `Mailing List <https://groups.google.com/group/getsentry>`_
* `IRC <irc://irc.freenode.net/sentry>`_  (irc.freenode.net, #sentry)
