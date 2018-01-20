<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use Http\Client\Common\Plugin;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;
use Raven\Processor\ProcessorInterface;

class ClientBuilderTest extends TestCase
{
    public function testCreate()
    {
        $clientBuilder = ClientBuilder::create();

        $this->assertInstanceOf(ClientBuilder::class, $clientBuilder);
    }

    public function testSetUriFactory()
    {
        /** @var UriFactory|\PHPUnit_Framework_MockObject_MockObject $uriFactory */
        $uriFactory = $this->getMockBuilder(UriFactory::class)
            ->getMock();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->setUriFactory($uriFactory);

        $this->assertAttributeSame($uriFactory, 'uriFactory', $clientBuilder);
    }

    public function testSetMessageFactory()
    {
        /** @var MessageFactory|\PHPUnit_Framework_MockObject_MockObject $messageFactory */
        $messageFactory = $this->getMockBuilder(MessageFactory::class)
            ->getMock();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->setMessageFactory($messageFactory);

        $this->assertAttributeSame($messageFactory, 'messageFactory', $clientBuilder);

        $client = $clientBuilder->getClient();

        $this->assertAttributeSame($messageFactory, 'requestFactory', $client);
    }

    public function testSetHttpClient()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->setHttpClient($httpClient);

        $this->assertAttributeSame($httpClient, 'httpClient', $clientBuilder);

        $client = $this->getObjectAttribute($clientBuilder->getClient(), 'httpClient');

        $this->assertAttributeSame($httpClient, 'client', $client);
    }

    public function testAddHttpClientPlugin()
    {
        /** @var Plugin|\PHPUnit_Framework_MockObject_MockObject $plugin */
        $plugin = $this->getMockBuilder(Plugin::class)
            ->getMock();

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

    public function testAddProcessor()
    {
        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);

        $clientBuilder = new ClientBuilder();
        $clientBuilder->addProcessor($processor, -10);

        $this->assertAttributeContains([$processor, -10], 'processors', $clientBuilder);
    }

    public function testRemoveProcessor()
    {
        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);

        $clientBuilder = new ClientBuilder();
        $clientBuilder->addProcessor($processor, -10);
        $clientBuilder->removeProcessor($processor);

        $this->assertAttributeNotContains([$processor, -10], 'processors', $clientBuilder);
    }

    public function testGetProcessors()
    {
        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);

        $clientBuilder = new ClientBuilder();
        $clientBuilder->addProcessor($processor, -10);
        $clientBuilder->addProcessor($processor, 10);

        $this->assertContains([$processor, -10], $clientBuilder->getProcessors());
        $this->assertContains([$processor, 10], $clientBuilder->getProcessors());
    }

    public function testGetClient()
    {
        $clientBuilder = new ClientBuilder();

        $this->assertInstanceOf(Client::class, $clientBuilder->getClient());
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
        $configuration = $this->getMockBuilder(Configuration::class)
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
            ['setIsTrustXForwardedProto', true],
            ['setPrefixes', ['foo', 'bar']],
            ['setSerializeAllObjects', false],
            ['setSampleRate', 0.5],
            ['setInstallDefaultBreadcrumbHandlers', false],
            ['setInstallShutdownHandler', false],
            ['setAutoLogStacks', false],
            ['setContextLines', 0],
            ['setEncoding', 'gzip'],
            ['setCurrentEnvironment', 'test'],
            ['setEnvironments', ['default']],
            ['setExcludedLoggers', ['foo', 'bar']],
            ['setExcludedExceptions', ['foo', 'bar']],
            ['setExcludedProjectPaths', ['foo', 'bar']],
            ['setTransport', null],
            ['setProjectRoot', 'foo'],
            ['setLogger', 'bar'],
            ['setProxy', 'foo'],
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
