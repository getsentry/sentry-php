Monolog
=======

Monolog supports Sentry out of the box, so you'll just need to configure a handler:

.. sourcecode:: php

    $client = new Raven_Client('___DSN___')

    $handler = new Monolog\Handler\RavenHandler($client);
    $handler->setFormatter(new Monolog\Formatter\LineFormatter("%message% %context% %extra%\n"));

    $monolog->pushHandler($handler);

Adding Context
--------------

Capturing context can be done via a monolog processor:

.. sourcecode:: php

    $monolog->pushProcessor(function ($record) {
        // record the current user
        $user = Acme::getCurrentUser();
        $record['user'] = array(
            'name' => $user->getName(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        );

        // Add various tags
        $record['context']['tags'] = array('key' => 'value');

        // Add various generic context
        $record['extra']['key'] = 'value';

        return $record;
    });
