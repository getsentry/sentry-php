Symfony
=======

Symfony is supported via the `sentry-symfony <https://github.com/getsentry/sentry-symfony>`_ package as a native bundle.

Symfony 2+
----------

Install the ``sentry/sentry-symfony`` package:

.. code-block:: bash

    $ composer require sentry/sentry-symfony


Enable the bundle in ``app/AppKernel.php``:

.. code-block:: php

    <?php
    class AppKernel extends Kernel
    {
        public function registerBundles()
        {
            $bundles = array(
                // ...

                new Sentry\SentryBundle\SentryBundle(),
            );

            // ...
        }

        // ...
    }

Add your DSN to ``app/config/config.yml``:

.. code-block:: yaml

    sentry:
        dsn: "___PUBLIC_DSN___"
