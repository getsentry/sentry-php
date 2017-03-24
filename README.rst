sentry-php
==========

.. image:: https://secure.travis-ci.org/getsentry/sentry-php.png?branch=master
   :target: http://travis-ci.org/getsentry/sentry-php
   :alt: Build Status

.. image:: https://img.shields.io/packagist/dt/sentry/sentry.svg?style=flat-square
   :target: https://packagist.org/packages/sentry/sentry
   :alt: Total Downloads

.. image:: https://img.shields.io/packagist/dm/sentry/sentry.svg?style=flat-square
   :target: https://packagist.org/packages/sentry/sentry
   :alt: Downloads per month

.. image:: https://img.shields.io/packagist/v/sentry/sentry.svg?style=flat-square
   :target: https://packagist.org/packages/sentry/sentry
   :alt: Latest stable version

.. image:: http://img.shields.io/packagist/l/sentry/sentry.svg?style=flat-square
   :target: https://packagist.org/packages/sentry/sentry
   :alt: License


The official PHP SDK for `Sentry <https://getsentry.com/>`_.

.. code-block:: php

    // Instantiate a new client with a compatible DSN and install built-in
    // handlers
    $client = (new Raven_Client('http://public:secret@example.com/1'))->install();

    // Capture an exception
    $event_id = $client->captureException($ex);

    // Give the user feedback
    echo "Sorry, there was an error!";
    echo "Your reference ID is " . $event_id;

For more information, see our `documentation <https://docs.getsentry.com/hosted/clients/php/>`_.


Contributing
------------

Dependencies are managed through composer:

::

    $ composer install


Tests can then be run via phpunit:

::

    $ vendor/bin/phpunit


Resources
---------

* `Documentation <https://docs.getsentry.com/hosted/clients/php/>`_
* `Bug Tracker <http://github.com/getsentry/sentry-php/issues>`_
* `Code <http://github.com/getsentry/sentry-php>`_
* `Mailing List <https://groups.google.com/group/getsentry>`_
* `IRC <irc://irc.freenode.net/sentry>`_  (irc.freenode.net, #sentry)
