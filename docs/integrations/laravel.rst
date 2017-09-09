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

Finally, if you wish to wire up User Feedback, you can do so by creating a custom
error response. To do this, open up ``App/Exceptions/Handler.php`` and except the
``render`` method:

.. code-block:: php

    <?php

    class Handler extends ExceptionHandler
    {
        private $sentryID;

        public function report(Exception $e)
        {
            if ($this->shouldReport($e)) {
                // bind the event ID for Feedback
                $this->sentryID = app('sentry')->captureException($e);
            }
            parent::report($e);
        }

        // ...
        public function render($request, Exception $e)
        {
            return response()->view('errors.500', [
                'sentryID' => $this->sentryID,
            ], 500);
        }
    }

Next, create ``resources/views/errors/500.blade.php``, and embed the feedback code:

.. code-block:: html

    <div class="content">
        <div class="title">Something went wrong.</div>
        @unless(empty($sentryID))
            <!-- Sentry JS SDK 2.1.+ required -->
            <script src="https://cdn.ravenjs.com/3.3.0/raven.min.js"></script>

            <script>
            Raven.showReportDialog({
                eventId: '{{ $sentryID }}',

                // use the public DSN (dont include your secret!)
                dsn: '___PUBLIC_DSN___'
            });
            </script>
        @endunless
    </div>

That's it!

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

If you wish to wire up Sentry anywhere outside of the standard error handlers, or
if you need to configure additional settings, you can access the Sentry instance
through ``$app``:

.. code-block:: php

    $app['sentry']->setRelease(Git::sha());

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

Testing with Artisan
--------------------

You can test your configuration using the provided ``artisan`` command:

.. code-block:: bash

    $ php artisan sentry:test
    [sentry] Client configuration:
    -> server: https://app.getsentry.com/api/3235/store/
    -> project: 3235
    -> public_key: e9ebbd88548a441288393c457ec90441
    -> secret_key: 399aaee02d454e2ca91351f29bdc3a07
    [sentry] Generating test event
    [sentry] Sending test event with ID: 5256614438cf4e0798dc9688e9545d94

Adding Context
--------------

The mechanism to add context will vary depending on which version of Laravel you're using, but the general approach is the same. Find a good entry point to your application in which the context you want to add is available, ideally early in the process.

In the following example, we'll use a middleware:

.. code-block:: php

    namespace App\Http\Middleware;

    use Closure;

    class SentryContext
    {
        /**
         * Handle an incoming request.
         *
         * @param  \Illuminate\Http\Request $request
         * @param  \Closure                 $next
         *
         * @return mixed
         */
        public function handle($request, Closure $next)
        {
            if (app()->bound('sentry')) {
                /** @var \Raven\Client $sentry */
                $sentry = app('sentry');

                // Add user context
                if (auth()->check()) {
                    $sentry->setUserContext([...]);
                } else {
                    $sentry->setUserContext(['id' => null]);
                }

                // Add tags context
                $sentry->tags_context([...]);
            }

            return $next($request);
        }
    }

Configuration
-------------

The following settings are available for the client:

.. describe:: dsn

    The DSN to authenticate with Sentry.

    .. code-block:: php

        'dsn' => '___DSN___',

.. describe:: release

    The version of your application (e.g. git SHA)

    .. code-block:: php

        'release' => MyApp::getReleaseVersion(),


.. describe:: breadcrumbs.sql_bindings

    Capture bindings on SQL queries.

    Defaults to ``true``.

    .. code-block:: php

        'breadcrumbs.sql_bindings' => false,


.. describe:: setUserContext

    Capture setUserContext automatically.

    Defaults to ``true``.

    .. code-block:: php

        'setUserContext' => false,

