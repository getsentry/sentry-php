<?php

namespace Raven\Tests\Transport;

use Http\Client\Curl\Client;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Raven\Transport\CurlTransportFactory;

class CurlTransportFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetInstance()
    {
        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        /** @var StreamFactory|\PHPUnit_Framework_MockObject_MockObject $streamFactory */
        $streamFactory = $this->getMockBuilder(StreamFactory::class)
            ->getMock();

        $transportFactory = new CurlTransportFactory($messageFactory, $streamFactory);
        $client = $transportFactory->getInstance(['foo' => 'bar']);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertAttributeSame($messageFactory, 'messageFactory', $client);
        $this->assertAttributeSame($streamFactory, 'streamFactory', $client);
        $this->assertAttributeEquals(['foo' => 'bar'], 'options', $client);
    }
}