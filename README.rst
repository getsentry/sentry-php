raven-php
=========

.. image:: https://secure.travis-ci.org/getsentry/raven-php.png?branch=master
   :target: http://travis-ci.org/getsentry/raven-php


raven-php is an experimental PHP client for `Sentry <http://aboutsentry.com/>`_.

::

    // Instantiate a new client with a compatible DSN
    $client = new Raven_Client('http://public:secret@example.com/1');

    // Capture a message
    $event_id = $client->getIdent($client->captureMessage('my log message'));

    // Capture an exception
    $event_id = $client->getIdent($client->captureException($ex));

    // Give the user feedback
    echo "Sorry, there was an error!";
    echo "Your reference ID is " . $event_id;

    // Install error handlers
    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();

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

And include it in your scripts:

::

    require_once '/path/to/Raven/library/Raven.php';

Or, alternatively use the autoloader:

::

    require_once '/path/to/Raven/library/Raven/Autoloader.php';
    Raven_Autoloader::register();

Or, if you're using `Composer <https://github.com/composer/composer>`_:

::

    require_once 'vendor/autoload.php';
    

Resources
---------

* `Bug Tracker <http://github.com/getsentry/raven-php/issues>`_
* `Code <http://github.com/getsentry/raven-php>`_
* `Mailing List <https://groups.google.com/group/getsentry>`_
* `IRC <irc://irc.freenode.net/sentry>`_  (irc.freenode.net, #sentry)
