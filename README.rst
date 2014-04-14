raven-php
=========

.. image:: https://secure.travis-ci.org/getsentry/raven-php.png?branch=master
   :target: http://travis-ci.org/getsentry/raven-php


raven-php is a PHP client for `Sentry <http://aboutsentry.com/>`_.

.. code-block:: php

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

If you're using `Composer <https://getcomposer.org/>`_ to manage
dependencies, you can add Raven with it.

.. code-block:: json

    {
        "require": {
            "raven/raven": "$VERSION"
        }
    }

(replace ``$VERSION`` with one of the available versions on `Packagist <https://packagist.org/packages/raven/raven>`_)
or to get the latest version off the master branch:

.. code-block:: json

    {
        "require": {
            "raven/raven": "dev-master"
        }
    }

Note that using unstable versions is not recommended and should be avoided. Also
you should define a maximum version, e.g. by doing ``>=0.6,<1.0`` or ``~0.6``.

Composer will take care of the autoloading for you, so if you require the
``vendor/autoload.php``, you're good to go.


Install source from GitHub
~~~~~~~~~~~~~~~~~~~~~~~~~~

To install the source code:

::

    $ git clone git://github.com/getsentry/raven-php.git

And including it using the autoloader:

.. code-block:: php

    require_once '/path/to/Raven/library/Raven/Autoloader.php';
    Raven_Autoloader::register();


Configuration
-------------

Several options exist that allow you to configure the behavior of the ``Raven_Client``. These are passed as the
second parameter of the constructor, and is expected to be an array of key value pairs:

.. code-block:: php

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

.. code-block:: php

    'tags' => array(
        'php_version' => phpversion(),
    )


``trace``
~~~~~~~~~

Set this to ``false`` to disable reflection tracing (function calling arguments) in stacktraces.


``logger``
~~~~~~~~~~

Adjust the default logger name for messages.

Defaults to ``php``.


Providing Request Context
-------------------------

Most of the time you're not actually calling out to Raven directly, but you still want to provide some additional context. This lifecycle generally constists of something like the following:

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


If you're performing additional requests during the lifecycle, you'll also need to ensure you cleanup the context (to reset its state):

.. code-block:: php

    $client->context->clear();


Contributing
------------

First, make sure you can run the test suite. Install development dependencies :

::

    $ composer install
    
You may now use phpunit :

::

    $ bin/phpunit



Resources
---------

* `Bug Tracker <http://github.com/getsentry/raven-php/issues>`_
* `Code <http://github.com/getsentry/raven-php>`_
* `Mailing List <https://groups.google.com/group/getsentry>`_
* `IRC <irc://irc.freenode.net/sentry>`_  (irc.freenode.net, #sentry)
