Laravel
=======

Laravel is supported via a native extension, `sentry-laravel <https://github.com/getsentry/sentry-laravel>`_.

Laravel 5.x
-----------

Install the ``sentry/sentry-laravel`` package:

.. code-block:: bash

    $ composer require sentry/sentry-laravel

Add the Sentry service provider and facade in ``config/app.php``:

.. code-block:: php

    'providers' => array(
        // ...
        Sentry\SentryLaravel\SentryLaravelServiceProvider::class,
    )

    'aliases' => array(
        // ...
        'Sentry' => Sentry\SentryLaravel\SentryFacade::class,
    )


Add Sentry reporting to ``App/Exceptions/Handler.php``:

.. code-block:: php

    public function report(Exception $e)
    {
        if ($this->shouldReport($e)) {
            app('sentry')->captureException($e);
        }
        parent::report($e);
    }

Create the Sentry configuration file (``config/sentry.php``):

.. code-block:: bash

    $ php artisan vendor:publish --provider="Sentry\SentryLaravel\SentryLaravelServiceProvider"


Add your DSN to ``.env``:

.. code-block:: bash

    SENTRY_DSN=___DSN___

Laravel 4.x
-----------

Install the ``sentry/sentry-laravel`` package:

.. code-block:: bash

    $ composer require sentry/sentry-laravel

Add the Sentry service provider and facade in ``config/app.php``:

.. code-block:: php

    'providers' => array(
        // ...
        'Sentry\SentryLaravel\SentryLaravelServiceProvider',
    )

    'aliases' => array(
        // ...
        'Sentry' => 'Sentry\SentryLaravel\SentryFacade',
    )


Create the Sentry configuration file (``config/sentry.php``):

.. code-block:: php

    $ php artisan config:publish sentry/sentry-laravel

Add your DSN to ``config/sentry.php``:

.. code-block:: php

    <?php

    return array(
        'dsn' => '___DSN___',

        // ...
    );

Lumen 5.x
---------

Install the ``sentry/sentry-laravel`` package:

.. code-block:: bash

    $ composer require sentry/sentry-laravel

Register Sentry in ``bootstrap/app.php``:

.. code-block:: php

    $app->register('Sentry\SentryLaravel\SentryLumenServiceProvider');

    # Sentry must be registered before routes are included
    require __DIR__ . '/../app/Http/routes.php';

Add Sentry reporting to ``app/Exceptions/Handler.php``:

.. code-block:: php

    public function report(Exception $e)
    {
        if ($this->shouldReport($e)) {
            app('sentry')->captureException($e);
        }
        parent::report($e);
    }

Create the Sentry configuration file (``config/sentry.php``):

.. code-block:: php

    <?php

    return array(
        'dsn' => '___DSN___',

    // capture release as git sha
    // 'release' => trim(exec('git log --pretty="%h" -n1 HEAD')),
    );
