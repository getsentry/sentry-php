.. sentry:edition:: self

   Sentry-PHP
   ==========

.. sentry:edition:: on-premise, hosted

    .. class:: platform-php

    PHP
    ===

The PHP SDK for Sentry supports PHP 5.3 and higher.  It's
available as a BSD licensed Open Source library.

Installation
------------

There are various ways to install the PHP integration for Sentry.  The
recommended way is to use `Composer <http://getcomposer.org/>`__::

    $ composer require "sentry/sentry"

Configuration
-------------

The most important part is the creation of the raven client.  Create it
once and reference it from anywhere you want to interface with Sentry:

.. code-block:: php

    $client = new \Raven\Client('___DSN___');

Once you have the client you can either use it manually or enable the
automatic error and exception capturing which is recomended:

.. code-block:: php

    $error_handler = new \Raven\ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();

Adding Context
--------------

Much of the usefulness of Sentry comes from additional context data with
the events.  The PHP client makes this very convenient by providing
methods to set thread local context data that is then submitted
automatically with all events.  For instance you can use the
``setUserContext`` method to add information about the current user:

.. sourcecode:: php

    $client->setUserContext(array(
        'email' => $USER->getEmail()
    ));

For more information see :ref:`sentry-php-request-context`.

Deep Dive
---------

Want more?  Have a look at the full documentation for more information.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   usage
   config
   integrations/index

Resources:

* `Bug Tracker <http://github.com/getsentry/sentry-php/issues>`_
* `Github Project <http://github.com/getsentry/sentry-php>`_
