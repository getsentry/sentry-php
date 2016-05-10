Advanced Topics
===============

This covers various advanced usage topics for the PHP client.

.. _sentry-php-advanced-installation:

Advanced Installation
---------------------

This covers all methods of installation and a bit more detail.

Install with Composer
`````````````````````

If you're using `Composer <https://getcomposer.org/>`_ to manage
dependencies, you can add the Sentry SDK with it.

.. code-block:: json

    {
        "require": {
            "sentry/sentry": "$VERSION"
        }
    }

(replace ``$VERSION`` with one of the available versions on `Packagist
<https://packagist.org/packages/sentry/sentry>`_) or to get the latest
version off the master branch:

.. code-block:: json

    {
        "require": {
            "sentry/sentry": "dev-master"
        }
    }

Note that using unstable versions is not recommended and should be
avoided. Also you should define a maximum version, e.g. by doing
``>=0.6,<1.0`` or ``~0.6``.

Composer will take care of the autoloading for you, so if you require the
``vendor/autoload.php``, you're good to go.

Install Source from GitHub
``````````````````````````

To install the source code::

    $ git clone git://github.com/getsentry/sentry-php.git

And including it using the autoloader:

.. code-block:: php

    require_once '/path/to/Raven/library/Raven/Autoloader.php';
    Raven_Autoloader::register();
