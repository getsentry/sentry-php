<?php

use Guzzle\Plugin\Log\LogPlugin;
use Raven\Plugin\CaptureHttpInterfacePlugin;
use Raven\Request\Factory\SymfonyHttpFactory;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/vendor/autoload.php';

$request = Request::create('http://helloworld.com/app_dev.php/hello');

$client = Raven\Client::create(array(
    'dsn' => 'http://0de7eceaa7a342e8846db675d8faef02:c1bd869c9a704bbe93b7570ec446542e@sentry.hautelook.net/2',
));
$client->addSubscriber(LogPlugin::getDebugPlugin());
$client->addSubscriber(new CaptureHttpInterfacePlugin(new SymfonyHttpFactory($request)));

$client->capture(array(
    'message' => 'HELLO THERE',
));

$client->capture(array(
    'message' => 'Message message',
    'sentry.interfaces.Message' => new \Raven\Request\Interfaces\Message('hello %s', array('Adrien')),
));

try {


    try {
        Raven\Client::create(array(
            'dsn' => 'http://',
        ));
    } catch (\Exception $e) {
        throw new \RuntimeException('oh my oooo', 0, $e);
    }
} catch (\Exception $e) {
    $exceptionFactory = new \Raven\Request\Factory\ExceptionFactory();
    $client->capture(array(
        'message' => $e->getMessage(),
        'culprit' => 'ooo',
        'sentry.interfaces.Exception' => $exceptionFactory->create($e),
    ));
}
