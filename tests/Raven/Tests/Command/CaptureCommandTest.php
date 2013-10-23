<?php

namespace Raven\Tests\Command;

use Hautelook\Frankenstein\TestCase;
use Prophecy\Argument;
use Raven\Client;

class CaptureCommandTest extends TestCase
{
    public function testMissingRequiredParameters()
    {
        $client = $this->createClient();

        $this
            ->exception(function () use ($client) {
                $client->getCommand('capture')->prepare();
            })
                ->isInstanceOf('Guzzle\Service\Exception\ValidationException')
        ;
    }

    public function testValidParameters()
    {
        $client = $this->createClient();

        $client
            ->getCommand('capture', array(
                'message' => 'Foo bar',
            ))
            ->prepare()
        ;

        $client
            ->getCommand('capture', array(
                'message' => 'Foo bar',
                'tags' => array(
                    'env' => 'dev',
                ),
                'modules' => array(
                    'symfony/symfony' => '2.4.0-dev',
                ),
            ))
            ->prepare()
        ;
    }

    private function createClient()
    {
        return Client::create(array(
            'public_key' => 'public',
            'secret_key' => 'secret',
            'project_id' => '1337',
        ));
    }
}
