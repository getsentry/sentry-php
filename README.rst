raven-php
=========

raven-php is an experimental PHP client for `Sentry <http://aboutsentry.com/>`_.

::

    // Register the autoloader
    require('/path/to/Raven/Autoloader.php');
    Raven_Autoloader::register();

    // Instantiate a new client with a compatible DSN
    $client = new Raven_Client('http://public:secret@example.com/1');

    // Capture a message
    $event_id = $client->getIdent($client->captureMessage('my log message'));

    // Capture an exception
    $event_id = $client->getIdent($client->captureException($ex));

    // Give the user feedback
    echo "Sorry, there was an error!";
    echo "Your reference ID is " . $event_id;

Resources
---------

* `Bug Tracker <http://github.com/getsentry/raven-php/issues>`_
* `Code <http://github.com/getsentry/raven-php>`_
* `IRC <irc://irc.freenode.net/sentry>`_  (irc.freenode.net, #sentry)
