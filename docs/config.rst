Configuration
=============

Several options exist that allow you to configure the behavior of the
``Raven_Client``. These are passed as the second parameter of the
constructor, and is expected to be an array of key value pairs:

.. code-block:: php

    $client = new Raven_Client($dsn, array(
        'option_name' => 'value',
    ));

Available Settings
------------------

The following settings are available for the client:

.. describe:: name

    A string to override the default value for the server's hostname.

    Defaults to ``Raven_Compat::gethostname()``.

.. describe:: tags

    An array of tags to apply to events in this context.

    .. code-block:: php

        'tags' => array(
            'php_version' => phpversion(),
        )

    .. code-block:: php

        $client->tags_context(array(
            'php_version' => phpversion(),
        ));

.. describe:: release

    The version of your application (e.g. git SHA)

    .. code-block:: php

        'release' => MyApp::getReleaseVersion(),

    .. code-block:: php

        $client->setRelease(MyApp::getReleaseVersion());

.. describe:: environment

    The environment your application is running in.

    .. code-block:: php

        'environment' => 'production',

    .. code-block:: php

        $client->setEnvironment('production');

.. describe:: app_path

    The root path to your application code.

    .. code-block:: php

        'app_path' => app_root(),

    .. code-block:: php

        $client->setAppPath(app_root());

.. describe:: excluded_app_paths

    Paths to exclude from app_path detection.

    .. code-block:: php

        'excluded_app_paths' => array(app_root() . '/cache'),

    .. code-block:: php

        $client->setExcludedAppPaths(array(app_root() . '/cache'));

.. describe:: prefixes

    Prefixes which should be stripped from filenames to create relative
    paths.

    .. code-block:: php

        'prefixes' => array(
            '/www/php/lib',
        ),

    .. code-block:: php

        $client->setPrefixes(array(
            '/www/php/lib',
        ));

.. describe:: sample_rate

    The sampling factor to apply to events. A value of 0.00 will deny sending
    any events, and a value of 1.00 will send 100% of events.

    .. code-block:: php

        // send 50% of events
        'sample_rate' => 0.5,

.. describe:: send_callback

    A function which will be called whenever data is ready to be sent. Within
    the function you can mutate the data, or alternatively return ``false`` to
    instruct the SDK to not send the event.

    .. code-block:: php

        'send_callback' => function($data) {
            // strip HTTP data
            @unset($data['request']);
        },

    .. code-block:: php

        $client->setSendCallback(function($data) {
            // dont send events if POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST')
            {
                return false;
            }
        });

.. describe:: curl_method

    Defaults to 'sync'.

    Available methods:

    - ``sync`` (default): send requests immediately when they're made
    - ``async``: uses a curl_multi handler for best-effort asynchronous
      submissions
    - ``exec``: asynchronously send events by forking a curl
      process for each item

.. describe:: curl_path

    Defaults to 'curl'.

    Specify the path to the curl binary to be used with the 'exec' curl
    method.

.. describe:: transport

    Set a custom transport to override how Sentry events are sent upstream.

    .. code-block:: php

        'transport' => function($client, $data) {
            $myHttpClient->send(array(
                'url'     => $client->getServerEndpoint(),
                'method'  => 'POST',
                'headers' => array(
                    'Content-Encoding' => 'gzip',
                    'Content-Type'     => 'application/octet-stream',
                    'User-Agent'       => $client->getUserAgent(),
                    'X-Sentry-Auth'    => $client->getAuthHeader(),
                ),
                'body'    => gzcompress(jsonEncode($data)),
            ))
        },

    .. code-block:: php

        $client->setTransport(...);

.. describe:: trace

    Set this to ``false`` to disable reflection tracing (function calling
    arguments) in stacktraces.


.. describe:: logger

    Adjust the default logger name for messages.

    Defaults to ``php``.

.. describe:: ca_cert

    The path to the CA certificate bundle.

    Defaults to the common bundle which includes getsentry.com:
    ./data/cacert.pem

    Caveats:

    - The CA bundle is ignored unless curl throws an error suggesting it
      needs a cert.
    - The option is only currently used within the synchronous curl
      transport.

.. describe:: curl_ssl_version

    The SSL version (2 or 3) to use.  By default PHP will try to determine
    this itself, although in some cases this must be set manually.

.. describe:: message_limit

    Defaults to 1024 characters.

    This value is used to truncate message and frame variables. However it
    is not guarantee that length of whole message will be restricted by
    this value.

.. describe:: processors

    An array of classes to use to process data before it is sent to
    Sentry. By default, ``Raven_Processor_SanitizeDataProcessor`` is used

.. describe:: processorOptions

    Options that will be passed on to a ``setProcessorOptions()`` function
    in a ``Raven_Processor`` sub-class before that Processor is added to
    the list of processors used by ``Raven_Client``

    An example of overriding the regular expressions in
    ``Raven_Processor_SanitizeDataProcessor`` is below:

    .. code-block:: php

        'processorOptions' => array(
            'Raven_Processor_SanitizeDataProcessor' => array(
                        'fields_re' => '/(user_password|user_token|user_secret)/i',
                        'values_re' => '/^(?:\d[ -]*?){15,16}$/'
                    )
        )

.. describe:: timeout

    The timeout for sending requests to the Sentry server in seconds, default is 2 seconds.

    .. code-block:: php

        'timeout' => 2,

.. describe:: excluded_exceptions

    Exception that should not be reported, exceptions extending exceptions in this list will also
    be excluded, default is an empty array.

    In the example below, when you exclude ``LogicException`` you will also exclude ``BadFunctionCallException``
    since it extends ``LogicException``.

    .. code-block:: php

        'excluded_exceptions' => array('LogicException'),

.. describe:: ignore_server_port

    By default the server port will be added to the logged URL when it is a non
    standard port (80, 443).
    Setting this to ``true`` will ignore the server port altogether and will
    result in the server port never getting appended to the logged URL.

    .. code-block:: php

        'ignore_server_port' => true,

.. _sentry-php-request-context:

Providing Request Context
-------------------------

Most of the time you're not actually calling out to Raven directly, but
you still want to provide some additional context. This lifecycle
generally constists of something like the following:

- Set some context via a middleware (e.g. the logged in user)
- Send all given context with any events during the request lifecycle
- Cleanup context

There are three primary methods for providing request context:

.. code-block:: php

    // bind the logged in user
    $client->user_context(array('email' => 'foo@example.com'));

    // tag the request with something interesting
    $client->tags_context(array('interesting' => 'yes'));

    // provide a bit of additional context
    $client->extra_context(array('happiness' => 'very'));


If you're performing additional requests during the lifecycle, you'll also
need to ensure you cleanup the context (to reset its state):

.. code-block:: php

    $client->context->clear();
    
Processors
----------

The following processors are available bundled with sentry-php. They can be used in ``processors`` configuration, and configured through ``processorOptions`` as described above.

.. describe:: Raven_Processor_SanitizeDataProcessor

    This is the default processor. It replaces fields or values with asterisks
    in frames, http, and basic extra data.
   
   Available options:
   
   - ``fields_re``: takes a regex expression of fields to sanitize
     Defaults to ``/(authorization|password|passwd|secret|password_confirmation|card_number|auth_pw)/i``
   - ``values_re``: takes a regex expression of values to sanitize
     Defaults to ``/^(?:\d[ -]*?){13,16}$/``

.. describe:: Raven_Processor_SanitizeHttpHeadersProcessor

   This processor sanitizes the configured HTTP headers to ensure no sensitive
   information is sent to the server.
   
   Available options:
   
   - ``sanitize_http_headers``: takes an array of headers to sanitize. 
     Defaults to ``['Authorization', 'Proxy-Authorization', 'X-Csrf-Token', 'X-CSRFToken', 'X-XSRF-TOKEN']``

.. describe:: Raven_Processor_SanitizeStacktraceProcessor

   This processor removes the `pre_context`, `context_line` and `post_context`
   information from all exceptions captured by an event.

.. describe:: Raven_Processor_RemoveHttpBodyProcessor

   This processor removes all the data of the HTTP body to ensure no sensitive
   information is sent to the server in case the request method is POST, PUT,
   PATCH or DELETE.
 
.. describe:: Raven_Processor_RemoveCookiesProcessor
 
   This processor removes all the cookies from the request to ensure no sensitive
   information is sent to the server.
