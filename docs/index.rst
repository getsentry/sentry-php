.. sentry:edition:: self

   Raven-PHP
   =========

.. sentry:edition:: on-premise, hosted

    .. class:: platform-php

    PHP
    ===

Raven-PHP is a PHP client for Sentry supporting PHP 5.3 or higher.  It'a
available as a BSD licensed Open Source library.

Installation
------------

There are various ways to install the PHP integration for Sentry.  The
recommended way is to use `Composer <http://getcomposer.org/>`__::

    $ composer require "raven/raven"

Alternatively you can manually install it:

1.  Download and extract the latest `raven-php
    <https://github.com/getsentry/raven-php/archive/master.zip>`__ archive
    to your PHP project.
2.  Require the autoloader in your application:

    .. sourcecode:: php

        require_once '/path/to/Raven/library/Raven/Autoloader.php';
        Raven_Autoloader::register();

For more methods have a look at :ref:`raven-php-advanced-installation`.

Configuration
-------------

The most important part is the creation of the raven client.  Create it
once and reference it from anywhere you want to interface with Sentry:

.. code-block:: php

    $client = new Raven_Client('___DSN___');

Once you have the client you can either use it manually or enable the
automatic error and exception capturing which is recomended:

.. code-block:: php

    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();

Adding Context
--------------

Much of the usefulness of Sentry comes from additional context data with
the events.  The PHP client makes this very convenient by providing
methods to set thread local context data that is then submitted
automatically with all events.  For instance you can use the
``user_context`` method to add information about the current user:

.. sourcecode:: php

    $client->user_context(array(
        'email' => $USER->getEmail()
    ));

For more information see :ref:`raven-php-request-context`.

Deep Dive
---------

Want more?  Have a look at the full documentation for more information.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   advanced
   usage
   config
   integrations/index

Resources:

* `Bug Tracker <http://github.com/getsentry/raven-php/issues>`_
* `Github Project <http://github.com/getsentry/raven-php>`_
