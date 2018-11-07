<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests;

use Http\Client\Common\Plugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Sentry\Transport\TransportInterface;

class ClientBuilderTest extends TestCase
{
    public function testCreate()
    {
        $clientBuilder = ClientBuilder::create();

        $this->assertInstanceOf(ClientBuilder::class, $clientBuilder);
    }

    public function testHttpTransportIsUsedWhenServeIsConfigured()
    {
        $clientBuilder = new ClientBuilder(['dsn' => 'http://public:secret@example.com/sentry/1']);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testNullTransportIsUsedWhenNoServerIsConfigured()
    {
        $clientBuilder = new ClientBuilder();

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertInstanceOf(NullTransport::class, $transport);
    }

    public function testSetUriFactory()
    {
        /** @var UriFactory|\PHPUnit_Framework_MockObject_MockObject $uriFactory */
        $uriFactory = $this->createMock(UriFactory::class);

        $clientBuilder = new ClientBuilder(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setUriFactory($uriFactory);

        $this->assertAttributeSame($uriFactory, 'uriFactory', $clientBuilder);
    }

    public function testSetMessageFactory()
    {
        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->createMock(MessageFactory::class);

        $clientBuilder = new ClientBuilder(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setMessageFactory($messageFactory);

        $this->assertAttributeSame($messageFactory, 'messageFactory', $clientBuilder);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertAttributeSame($messageFactory, 'requestFactory', $transport);
    }

    public function testSetTransport()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $clientBuilder = new ClientBuilder(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setTransport($transport);

        $this->assertAttributeSame($transport, 'transport', $clientBuilder);
        $this->assertAttributeSame($transport, 'transport', $clientBuilder->getClient());
    }

    public function testSetHttpClient()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);

        $clientBuilder = new ClientBuilder(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setHttpClient($httpClient);

        $this->assertAttributeSame($httpClient, 'httpClient', $clientBuilder);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertAttributeSame($httpClient, 'client', $this->getObjectAttribute($transport, 'httpClient'));
    }

    public function testAddHttpClientPlugin()
    {
        /** @var Plugin|\PHPUnit_Framework_MockObject_MockObject $plugin */
        $plugin = $this->createMock(Plugin::class);

        $clientBuilder = new ClientBuilder();
        $clientBuilder->addHttpClientPlugin($plugin);

        $plugins = $this->getObjectAttribute($clientBuilder, 'httpClientPlugins');

        $this->assertCount(1, $plugins);
        $this->assertSame($plugin, $plugins[0]);
    }

    public function testRemoveHttpClientPlugin()
    {
        $plugin = new PluginStub1();
        $plugin2 = new PluginStub2();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->addHttpClientPlugin($plugin);
        $clientBuilder->addHttpClientPlugin($plugin);
        $clientBuilder->addHttpClientPlugin($plugin2);

        $this->assertAttributeCount(3, 'httpClientPlugins', $clientBuilder);

        $clientBuilder->removeHttpClientPlugin(PluginStub1::class);

        $plugins = $this->getObjectAttribute($clientBuilder, 'httpClientPlugins');

        $this->assertCount(1, $plugins);
        $this->assertSame($plugin2, reset($plugins));
    }

    public function testGetClient()
    {
        $clientBuilder = new ClientBuilder(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertAttributeInstanceOf(HttpTransport::class, 'transport', $client);

        $transport = $this->getObjectAttribute($client, 'transport');

        $this->assertAttributeSame($this->getObjectAttribute($clientBuilder, 'messageFactory'), 'requestFactory', $transport);
        $this->assertAttributeInstanceOf(PluginClient::class, 'httpClient', $transport);

        $httpClientPlugin = $this->getObjectAttribute($transport, 'httpClient');

        $this->assertAttributeSame($this->getObjectAttribute($clientBuilder, 'httpClient'), 'client', $httpClientPlugin);
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage The method named "methodThatDoesNotExists" does not exists.
     */
    public function testCallInvalidMethodThrowsException()
    {
        $clientBuilder = new ClientBuilder();
        $clientBuilder->methodThatDoesNotExists();
    }

    /**
     * @dataProvider optionsDataProvider
     */
    public function testCallExistingMethodForwardsCallToConfiguration($setterMethod, $value)
    {
        $configuration = $this->getMockBuilder(Options::class)
            ->getMock();

        $configuration->expects($this->once())
            ->method($setterMethod)
            ->with($this->equalTo($value));

        $clientBuilder = new ClientBuilder();

        $reflectionProperty = new \ReflectionProperty(ClientBuilder::class, 'configuration');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($clientBuilder, $configuration);
        $reflectionProperty->setAccessible(false);

        $clientBuilder->$setterMethod($value);
    }

    public function optionsDataProvider()
    {
        return [
            ['setPrefixes', ['foo', 'bar']],
            ['setSerializeAllObjects', false],
            ['setSampleRate', 0.5],
            ['setMbDetectOrder', ['foo', 'bar']],
            ['setAutoLogStacks', false],
            ['setContextLines', 0],
            ['setEncoding', 'gzip'],
            ['setCurrentEnvironment', 'test'],
            ['setEnvironments', ['default']],
            ['setExcludedLoggers', ['foo', 'bar']],
            ['setExcludedExceptions', ['foo', 'bar']],
            ['setExcludedProjectPaths', ['foo', 'bar']],
            ['setProjectRoot', 'foo'],
            ['setLogger', 'bar'],
            ['setRelease', 'dev'],
            ['setServerName', 'example.com'],
            ['setTags', ['foo', 'bar']],
            ['setErrorTypes', 0],
        ];
    }
}

class PluginStub1 implements Plugin
{
    public function handleRequest(RequestInterface $request, callable $next, callable $first)
    {
    }
}

class PluginStub2 implements Plugin
{
    public function handleRequest(RequestInterface $request, callable $next, callable $first)
    {
    }
}
