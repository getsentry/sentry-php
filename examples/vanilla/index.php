<?php

error_reporting(E_ALL);

require_once '../../lib/Raven/Autoloader.php';
Raven_Autoloader::register();


function setupSentry()
{
    $client = new \Raven_Client('https://e9ebbd88548a441288393c457ec90441:399aaee02d454e2ca91351f29bdc3a07@app.getsentry.com/3235');
    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();
}

function createError()
{
    1 / 0;
}

setupSentry();
createError();
