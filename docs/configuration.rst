Configuration options
#####################

The Raven client can be configured through a bunch of configuration options.
Some of them are automatically populated by environment variables when a new
instance of the ``Configuration`` class is created. The following is an exhaustive
list of them with a description of what they do and some example values.

Send attempts
=============

The number of attempts that should should be made to send an event before erroring
and dropping it from the queue.

.. code-block:: php

    $configuration = new Configuration(['send_attempts' => 1]);
    $configuration->setSendAttempts(1);

By default this option is set to 6.

Server
======

The DSN of the Sentry server the authenticated user is bound to.

.. code-block:: php

    // Before Sentry 1.9 the private DSN must be used
    $configuration = new Configuration(['server' => 'http://public:private@example.com/1']);

    // After Sentry 1.9 the public DSN must be used
    $configuration = new Configuration(['server' => 'http://public@example.com/1']);

By default this option is borrowed from the ``SENTRY_DSN`` environment variable,
if it exists. A value of ``null`` disables completely the sending of any event.
Since Sentry 1.9 the public DSN must be used instead of the private one, which
is deprecated and whose support will be removed in future releases of the server.

Server name
===========

The name of the server the SDK is running on (it's usually the hostname).

.. code-block:: php

    $configuration = new Configuration(['server_name' => 'foo']);
    $configuration->setServerName('foo');

By default this option is set to the hostname of the server the SDK is running
on retrieved from a call to the ``gethostname()`` method.

Release
=======

The release tag to be passed with every event sent to Sentry. This permits to
track in which versions of your application each error happens.

.. code-block:: php

    $configuration = new Configuration(['release' => '1.2.3']);
    $configuration->setRelease('1.2.3');

Logger
======

The name of the logger which creates the events.

.. code-block:: php

    $configuration = new Configuration(['logger' => 'foo']);
    $configuration->setLogger('foo');

By default this option is set to ``php``.

Excluded loggers
================

The list of logger 'progname's to exclude from breadcrumbs.

.. code-block:: php

    $configuration = new Configuration(['excluded_loggers' => ['foo']);
    $configuration->setExcludedLoggers(['foo']);

Project root
============

The root of the project source code. As Sentry is able to distinguish project
files from third-parties ones (e.g. vendors), this option can be configured to
mark the directory containing all the source code of the application.

.. code-block:: php

    $configuration = new Configuration(['project_root' => '/foo/bar']);
    $configuration->setProjectRoot('/foo/bar');

For example, assuming that the directory structure shown below exists, marking
the project root as ``project-folder/src/`` means that every file inside that
directory that is part of a stacktrace frame will be marked as "application
code".

.. code-block::

    project-folder/
    ├── vendor/
        ├── foo/
    ├── src/
        ├── bar/ <-- these are going to be marked as application files

Current environment
===================

The name of the current environment. There can be multiple environments per
application, and each event belongs to one of them.

.. code-block:: php

    $configuration = new Configuration(['current_environment' => 'foo']);
    $configuration->setCurrentEnvironment('foo');

Environments
============

The environments are a feature that allows events to be easily filtered in
Sentry. An application can have multiple environments, but just one is active
at the same time. This option let you configure the environments names: if
the current environment is not whitelisted here, any event tagged with it
won't be sent.

.. code-block:: php

    $configuration = new Configuration(['environments' => ['foo', 'bar']]);
    $configuration->setEnvironments(['foo', 'bar']);

Encoding
========

This option sets the encoding type of the requests sent to the Sentry server.
There are two supported values: ``json`` and ``gzip``. The first one sends data
using plain JSON, so the request size will be bigger. The second one compresses
the request using GZIP, which can use more CPU power but will reduce the size
of the payload.

.. code-block:: php

    $configuration = new Configuration(['encoding' => 'json']);
    $configuration->setEncoding('json');

By default this option is set to ``gzip``.

Context lines
=============

This option sets the number of lines of code context to capture. If ``null`` is
set as the value, no source code lines will be added to each stacktrace frame.

.. code-block:: php

    $configuration = new Configuration(['context_lines' => 3]);
    $configuration->setContextLines(3);

Stacktrace logging
==================

This option sets whether the stacktrace of the captured errors should be
automatically captured or not.

.. code-block:: php

    $configuration = new Configuration(['auto_log_stacks' => true]);
    $configuration->setAutoLogStacks(true);

By default this option is set to ``true``.

Excluded exceptions
===================

Sometimes you may want to skip capturing certain exceptions. This option sets
the FCQN of the classes of the exceptions that you don't want to capture. The
check is done using the ``instanceof`` operator against each item of the array
and if at least one of them passes the event will be recorded.

.. code-block:: php

    $configuration = new Configuration(['excluded_exceptions' => ['RuntimeException']);
    $configuration->setExcludedExceptions(['RuntimeException']);

Sample rate
===========

The sampling factor to apply to events. A value of 0 will deny sending any
events, and a value of 1 will send 100% of events.

.. code-block:: php

    $configuration = new Configuration(['sample_rate' => 1]);
    $configuration->setSampleRate(1);

By default this option is set to 1, so all events will be sent regardeless.

Excluded application paths
==========================

This option configures the list of paths to exclude from the `app_path` detection.

.. code-block:: php

    $configuration = new Configuration(['excluded_app_paths' => ['foo']);
    $configuration->setExcludedProjectPaths(['foo']);


Prefixes
========

This option sets the list of prefixes which should be stripped from the filenames
to create relative paths.

.. code-block:: php

    $configuration = new Configuration(['prefixes' => ['foo']);
    $configuration->setPrefixes(['foo']);

Should capture callback
=======================

This option sets a callable that will be called before sending an event and is
the last place where you can stop it from being sent.

.. code-block:: php

    $configuration = new Configuration(['should_capture' => function () { return true }]);
    $configuration->setShouldCapture(function () { return true });
