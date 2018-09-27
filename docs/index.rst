.. sentry:edition:: self

   Sentry-PHP
   ==========

.. sentry:edition:: on-premise, hosted

    .. class:: platform-php

    PHP
    ===

The PHP SDK for Sentry supports PHP 5.6 and higher and is available as a BSD
licensed Open Source library.

Installation
------------
To install the SDK you will need to be using Composer_ in your project. To install
it please see the `docs <https://getcomposer.org/download/>`_.

Sentry PHP is not tied to any specific library that sends HTTP messages. Instead,
it uses Httplug_ to let users choose whichever PSR-7 implementation and HTTP client
they want to use.

If you just want to get started quickly you should run the following command:

.. code-block:: bash

    php composer.phar require sentry/sentry php-http/curl-client guzzlehttp/psr7

This will install the library itself along with an HTTP client adapter that uses
cURL as transport method (provided by Httplug) and a PSR-7 implementation
(provided by Guzzle). You do not have to use those packages if you do not want to.
The SDK does not care about which transport method you want to use because it's
an implementation detail of your application. You may use any package that provides
`php-http/async-client-implementation`_ and `http-message-implementation`_.

If you want to use Guzzle as underlying HTTP client, you just need to run the
following command to install the adapter and Guzzle itself:

.. code-block:: bash

    php composer.phar require php-http/guzzle6-adapter

You can then use the client builder to create a Raven client instance that will
use the configured HTTP client based on Guzzle. The code looks like the one
below:

.. code-block:: php

    use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;
    use Sentry\ClientBuilder;

    require 'vendor/autoload.php';

    // The client will use a Guzzle client to send the HTTP requests
    $client = ClientBuilder::create(['server' => 'http://___PUBLIC_DSN___@example.com/1'])
        ->setHttpClient(new GuzzleClientAdapter())
        ->getClient();

If you want to have more control on the Guzzle HTTP client you can create the
instance yourself and tell the adapter to use it instead of creating one
on-the-fly. Please refer to the `HTTPlug Guzzle 6 Adapter documentation`_ for
instructions on how to do it.

Usage
-----

.. code-block:: php

    use Sentry\ClientBuilder;
    use Sentry\ErrorHandler;

    require 'vendor/autoload.php';

    // Instantiate the SDK with your DSN
    $client = ClientBuilder::create(['server' => 'http://___PUBLIC_DSN___@example.com/1'])->getClient();

    // Register error handler to automatically capture errors and exceptions
    ErrorHandler::register($client);

    // Capture an exception manually
    $eventId = $client->captureException(new \RuntimeException('Hello World!'));

    // Give the user feedback
    echo 'Sorry, there was an error!';
    echo 'Your reference ID is ' . $eventId;

Deep Dive
---------

Want more?  Have a look at the full documentation for more information.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   quickstart
   configuration
   transport
   middleware
   error_handling
   integrations/index

Resources:

* `Bug Tracker <http://github.com/getsentry/sentry-php/issues>`_
* `Github Project <http://github.com/getsentry/sentry-php>`_
* `Code <http://github.com/getsentry/sentry-php>`_
* `Mailing List <https://groups.google.com/group/getsentry>`_
* `IRC (irc.freenode.net, #sentry) <irc://irc.freenode.net/sentry>`_

.. _Httplug: https://github.com/php-http/httplug
.. _Composer: https://getcomposer.org
.. _php-http/async-client-implementation: https://packagist.org/providers/php-http/async-client-implementation
.. _http-message-implementation: https://packagist.org/providers/psr/http-message-implementation
.. _HTTPlug Guzzle 6 Adapter documentation: http://docs.php-http.org/en/latest/clients/guzzle6-adapter.html
