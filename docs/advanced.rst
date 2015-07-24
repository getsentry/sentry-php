Advanced Topics
===============

This covers various advanced usage topics for the PHP client.

.. _raven-php-advanced-installation:

Advanced Installation
---------------------

This covers all methods of installation and a bit more detail.

Install with Composer
`````````````````````

If you're using `Composer <https://getcomposer.org/>`_ to manage
dependencies, you can add Raven with it.

.. code-block:: json

    {
        "require": {
            "raven/raven": "$VERSION"
        }
    }

(replace ``$VERSION`` with one of the available versions on `Packagist
<https://packagist.org/packages/raven/raven>`_) or to get the latest
version off the master branch:

.. code-block:: json

    {
        "require": {
            "raven/raven": "dev-master"
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

    $ git clone git://github.com/getsentry/raven-php.git

And including it using the autoloader:

.. code-block:: php

    require_once '/path/to/Raven/library/Raven/Autoloader.php';
    Raven_Autoloader::register();
