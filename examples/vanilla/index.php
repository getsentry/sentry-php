<?php

error_reporting(E_ALL);

define('SENTRY_DSN', 'https://e9ebbd88548a441288393c457ec90441:399aaee02d454e2ca91351f29bdc3a07@app.getsentry.com/3235');

require_once '../../lib/Raven/Autoloader.php';
\Raven\Autoloader::register();

function setupSentry()
{
    $object = new \Raven\Client(SENTRY_DSN);
    $object->setAppPath(__DIR__)
           ->setRelease(\Raven\Client::VERSION)
           ->setPrefixes(array(__DIR__))
           ->install();
}

function createCrumbs()
{
    echo($undefined['foobar']);
    echo($undefined['bizbaz']);
}

function createError()
{
    1 / 0;
}


function createException()
{
    throw new Exception('example exception');
}

setupSentry();
createCrumbs();
createError();
createException();
