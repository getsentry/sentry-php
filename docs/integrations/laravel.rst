Laravel
=======

Laravel support is managed by the `sentry-laravel <https://github.com/getsentry/sentry-laravel>`_ package.

Laravel 5.x
-----------

Install the ``sentry/sentry-laravel`` package:

::

    $ composer require sentry/sentry-laravel:*

Add the Sentry service provider and facade in ``config/app.php``:

::

    'providers' => array(
        // ...
        Sentry\SentryLaravel\SentryLaravelServiceProvider::class,
    )

    'aliases' => array(
        // ...
        'Sentry' => Sentry\SentryLaravel\SentryFacade::class,
    )

Add Sentry reporting to ``App/Exceptions/Handler.php``:

::

    public function report(Exception $e)
    {
        app('sentry')->captureException($e);
        parent::report($e);
    }

Create the Sentry configuration file (``config/sentry.php``):

::

    $ php artisan vendor:publish --provider="Sentry\SentryLaravel\SentryLaravelServiceProvider"


Laravel 4.x
-----------

Install the ``sentry/sentry-laravel`` package:

::

    $ composer require sentry/sentry-laravel:*

Add the Sentry service provider and facade in ``config/app.php``:

::

    'providers' => array(
        // ...
        'Sentry\SentryLaravel\SentryLaravelServiceProvider',
    )

    'aliases' => array(
        // ...
        'Sentry' => 'Sentry\SentryLaravel\SentryFacade',
    )



Create the Sentry configuration file (``config/sentry.php``):

::

    $ php artisan config:publish sentry/sentry-laravel


Lumen 5.x
---------

Install the ``sentry/sentry-laravel`` package:

::

    $ composer require sentry/sentry-laravel:*

Register Sentry in ``bootstrap/app.php``:

::


    $app->register('Sentry\SentryLaravel\SentryLumenServiceProvider');

    # Sentry must be registered before routes are included
    require __DIR__ . '/../app/Http/routes.php';

Add Sentry reporting to ``app/Exceptions/Handler.php``:

::

    public function report(Exception $e)
    {
        app('sentry')->captureException($e);
        parent::report($e);
    }

Create the Sentry configuration file (``config/sentry.php``):

::

    <?php

    return array(
        'dsn' => '___DSN___',

        // capture release as git sha
        // 'release' => trim(exec('git log --pretty="%h" -n1 HEAD')),
    );
