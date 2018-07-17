Error handling
##############

To capture unhandled errors and exceptions you have to manually register the
error handler and associate it to an instance of the client. The easiest way
to do it is to simply call the ``ErrorHandler::register`` method and pass the
client instance as first argument.

.. code-block:: php

    use Raven\ErrorHandler;

    // Initialize the client

    ErrorHandler::register($client);

By default the error handler reserves 10 megabytes of memory to handle fatal
errors. You can customize the amount by specifying it in bytes as the second
argument. For example, the code below will reserve 20 megabytes of memory.

.. code-block:: php

    use Raven\ErrorHandler;

    // Initialize the client

    ErrorHandler::register($client, 20480);

For some frameworks or projects there are specific integrations provided both
officially and by third party users that automatically register the error
handlers. In that case please refer to their documentation.

Capture errors
==============

The error handler can be customized to set which error types should be captured
and sent to the Sentry server: you may want to report all the errors but capture
only some of them. For example, the code below will capture all errors except
``E_DEPRECATED`` and ``E_USER_DEPRECATED``. Note that the ``error_reporting``
PHP ini option will be respected and any other handler that was present before
the Sentry error handler was registered will still be called regardeless.

.. code-block:: php

    use Raven\ErrorHandler;

    // Initialize the client

    $errorHandler = ErrorHandler::register($client);
    $errorHandler->captureAt(E_ALL &~ E_DEPRECATED &~ E_USER_DEPRECATED, true);

While calling the ``ErrorHandler::captureAt`` method you can decide whether the
new mask will replace entirely the previous one or not by changing the value of
the second argument. For example, suppose that you first disable the capturing
of the ``E_DEPRECATED`` and ``E_USER_DEPRECATED`` error types and sometime later
you want to re-enable only the first type of errors. In this case you have two
ways to do the same thing: know in advance the old mask and replace it like you
did in the example above or set to ``false`` the ``$replace`` argument (this is
the default value) and the new value will be appended to the existing mask:

.. code-block:: php

    use Raven\ErrorHandler;

    // Initialize the client

    $errorHandler = ErrorHandler::register($client);
    $errorHandler->captureAt(E_ALL &~ E_DEPRECATED &~ E_USER_DEPRECATED, true);
    
    // some time later you decide to re-enable capturing of E_DEPRECATED errors

    $errorHandler->captureAt(E_DEPRECATED, false);

Capture breadcrumbs
===================

Sentry supports logging breadcrumbs, which are a set of steps that led to an
event. By default the client logs the URL of the page being visited as the
first breadcrumb (if the context in which the app is running is a web request).
To automate the capturing of errors as breadcrumbs (e.g. you may want to automate
logging of warnings leading to an event) you can register an additional error
handler the same way as before.

.. code-block:: php

    use Raven\BreadcrumbErrorHandler;

    // Initialize the client

    $errorHandler = BreadcrumbErrorHandler::register($client);
    $errorHandler->captureAt(E_WARNING | E_USER_WARNING, true);
