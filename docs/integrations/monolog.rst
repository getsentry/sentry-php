Monolog
=======

Monolog supports Sentry out of the box, so you'll just need to configure a handler:

.. sourcecode:: php

    $client = new Raven_Client('___DSN___')

    $handler = new Monolog\Handler\RavenHandler($client);
    $handler->setFormatter(new Monolog\Formatter\LineFormatter("%message% %context% %extra%\n"));

    $logger->pushHandler($handler);
