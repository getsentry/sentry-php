<?php

namespace Raven\Tests;

use Hautelook\Frankenstein\TestCase;
use Raven\Client;

class ClientTest extends TestCase
{
    public function testCreateClientWithDsn()
    {
        $client = Client::create(array(
            'dsn' => 'https://public:secret@sentryapp.com/yolo/1337'
        ));

        $this
            ->string($client->getBaseUrl(true))
                ->isEqualTo('https://sentryapp.com/yolo/api/1337/')
            ->string($client->getConfig('public_key'))
                ->isEqualTo('public')
            ->string($client->getConfig('secret_key'))
                ->isEqualTo('secret')
        ;
    }

    public function testCreateClientWithoutDsn()
    {
        $client = Client::create(array(
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
            'host' => 'sentryapp.com',
            'path' => '/yolo/',
        ));

        $this
            ->string($client->getBaseUrl(true))
                ->isEqualTo('https://sentryapp.com/yolo/api/1337/')
            ->string($client->getConfig('public_key'))
                ->isEqualTo('public')
            ->string($client->getConfig('secret_key'))
                ->isEqualTo('secret')
        ;
    }

    public function testFailingCreateClient()
    {
        $this
            ->exception(function () {
                $client = Client::create(array());
            })
                ->isInstanceOf('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException')
        ;
    }

    public function testClientPort()
    {
        $client = Client::create(array(
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
            'port' => 6666,
        ));

        $this
            ->string($client->getBaseUrl(true))
                ->contains(':6666')
        ;
    }

    public function testRequestOptions()
    {
        $client = Client::create(array(
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
            Client::REQUEST_OPTIONS => array(
                'foo' => 'bar',
            ),
        ));

        $this
            ->variable($client->getDefaultOption('foo'))
                ->isEqualTo('bar')
        ;
    }

    public function testCaptureCommand()
    {
        $client = Client::create(array(
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
        ));

        $command = $client->getCommand('capture', array(
            'message' => 'foo',
        ));
        $request = $command->prepare();

        $this
            ->string($request->getUrl(true)->getPath())
                ->isEqualTo('/api/1337/store/')
        ;
    }

    public function testCaptureCommandFilterData()
    {
        $client = Client::create(array(
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
        ));

        $command = $client->getCommand('capture', array(
            'message' => 'foo',
            'extra' => array(
                'password' => 'zomg password',
            ),
        ));
        $request = $command->prepare();

        $json = json_decode((string) $request->getBody(), true);
        $this
            ->string($json['extra']['password'])
                ->isEqualTo('********')
        ;
    }

    public function testIgnoreExceptions()
    {
        $client = Client::create(array(
            'host' => 'localhost:1',
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
            Client::CURL_OPTIONS => array(
                CURLOPT_CONNECTTIMEOUT => 0,
            ),

            'ignored_exceptions' => array(
                'InvalidArgumentException' => true,
                'RuntimeException' => false,
                'Exception',
            ),
        ));

        $this
            ->variable($client->captureException(new \Exception()))
                ->isNull()
            ->variable($client->captureException(new \InvalidArgumentException()))
                ->isNull()
            ->exception(function () use ($client) {
                // check that this exception is not ignored, and that the client tries to send it
                $client->captureException(new \RuntimeException());
            })
        ;
    }
}
