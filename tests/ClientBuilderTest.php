<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Client\Common\Plugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Sentry\Transport\TransportInterface;

final class ClientBuilderTest extends TestCase
{
    public function testCreate(): void
    {
        $clientBuilder = ClientBuilder::create();

        $this->assertInstanceOf(ClientBuilder::class, $clientBuilder);
    }

    public function testHttpTransportIsUsedWhenServeIsConfigured(): void
    {
        $clientBuilder = new ClientBuilder(new Options(['dsn' => 'http://public:secret@example.com/sentry/1']));

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testNullTransportIsUsedWhenNoServerIsConfigured(): void
    {
        $clientBuilder = new ClientBuilder(new Options());

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertInstanceOf(NullTransport::class, $transport);
    }

    public function testSetUriFactory(): void
    {
        /** @var UriFactory|MockObject $uriFactory */
        $uriFactory = $this->createMock(UriFactory::class);

        $clientBuilder = new ClientBuilder(new Options(['dsn' => 'http://public:secret@example.com/sentry/1']));
        $clientBuilder->setUriFactory($uriFactory);

        $this->assertAttributeSame($uriFactory, 'uriFactory', $clientBuilder);
    }

    public function testSetMessageFactory(): void
    {
        /** @var MessageFactory|MockObject $messageFactory */
        $messageFactory = $this->createMock(MessageFactory::class);

        $clientBuilder = new ClientBuilder(new Options(['dsn' => 'http://public:secret@example.com/sentry/1']));
        $clientBuilder->setMessageFactory($messageFactory);

        $this->assertAttributeSame($messageFactory, 'messageFactory', $clientBuilder);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertAttributeSame($messageFactory, 'requestFactory', $transport);
    }

    public function testSetTransport(): void
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $clientBuilder = new ClientBuilder(new Options(['dsn' => 'http://public:secret@example.com/sentry/1']));
        $clientBuilder->setTransport($transport);

        $this->assertAttributeSame($transport, 'transport', $clientBuilder);
        $this->assertAttributeSame($transport, 'transport', $clientBuilder->getClient());
    }

    public function testSetHttpClient(): void
    {
        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);

        $clientBuilder = new ClientBuilder(new Options(['dsn' => 'http://public:secret@example.com/sentry/1']));
        $clientBuilder->setHttpClient($httpClient);

        $this->assertAttributeSame($httpClient, 'httpClient', $clientBuilder);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertAttributeSame($httpClient, 'client', $this->getObjectAttribute($transport, 'httpClient'));
    }

    public function testAddHttpClientPlugin(): void
    {
        /** @var Plugin|MockObject $plugin */
        $plugin = $this->createMock(Plugin::class);

        $clientBuilder = new ClientBuilder(new Options());
        $clientBuilder->addHttpClientPlugin($plugin);

        $plugins = $this->getObjectAttribute($clientBuilder, 'httpClientPlugins');

        $this->assertCount(1, $plugins);
        $this->assertSame($plugin, $plugins[0]);
    }

    public function testRemoveHttpClientPlugin(): void
    {
        $plugin = new PluginStub1();
        $plugin2 = new PluginStub2();

        $clientBuilder = new ClientBuilder(new Options());
        $clientBuilder->addHttpClientPlugin($plugin);
        $clientBuilder->addHttpClientPlugin($plugin);
        $clientBuilder->addHttpClientPlugin($plugin2);

        $this->assertAttributeCount(3, 'httpClientPlugins', $clientBuilder);

        $clientBuilder->removeHttpClientPlugin(PluginStub1::class);

        $plugins = $this->getObjectAttribute($clientBuilder, 'httpClientPlugins');

        $this->assertCount(1, $plugins);
        $this->assertSame($plugin2, reset($plugins));
    }

    public function testGetClient(): void
    {
        $clientBuilder = new ClientBuilder(new Options(['dsn' => 'http://public:secret@example.com/sentry/1']));
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
    public function testCallInvalidMethodThrowsException(): void
    {
        $clientBuilder = new ClientBuilder(new Options());
        $clientBuilder->methodThatDoesNotExists();
    }

    /**
     * @dataProvider optionsDataProvider
     */
    public function testCallExistingMethodForwardsCallToConfiguration(string $setterMethod, $value): void
    {
        $options = $this->createMock(Options::class);
        $options->expects($this->once())
            ->method($setterMethod)
            ->with($this->equalTo($value));

        $clientBuilder = new ClientBuilder(new Options());

        $reflectionProperty = new \ReflectionProperty(ClientBuilder::class, 'options');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($clientBuilder, $options);
        $reflectionProperty->setAccessible(false);

        $clientBuilder->$setterMethod($value);
    }

    public function optionsDataProvider(): array
    {
        return [
            ['setPrefixes', ['foo', 'bar']],
            ['setSampleRate', 0.5],
            ['setAttachStacktrace', true],
            ['setContextLines', 0],
            ['setEnableCompression', false],
            ['setEnvironment', 'test'],
            ['setExcludedProjectPaths', ['foo', 'bar']],
            ['setExcludedExceptions', ['foo', 'bar']],
            ['setProjectRoot', 'foo'],
            ['setLogger', 'bar'],
            ['setRelease', 'dev'],
            ['setServerName', 'example.com'],
            ['setTags', ['foo', 'bar']],
            ['setErrorTypes', 0],
        ];
    }

    /**
     * @dataProvider getClientTogglesCompressionPluginInHttpClientDataProvider
     */
    public function testGetClientTogglesCompressionPluginInHttpClient(bool $enabled): void
    {
        $builder = ClientBuilder::create(new Options(['enable_compression' => $enabled, 'dsn' => 'http://public:secret@example.com/sentry/1']));
        $builder->getClient();

        $decoderPluginFound = false;

        foreach ($this->getObjectAttribute($builder, 'httpClientPlugins') as $plugin) {
            if ($plugin instanceof Plugin\DecoderPlugin) {
                $decoderPluginFound = true;

                break;
            }
        }

        $this->assertEquals($enabled, $decoderPluginFound);
    }

    public function getClientTogglesCompressionPluginInHttpClientDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }
}

final class PluginStub1 implements Plugin
{
    public function handleRequest(RequestInterface $request, callable $next, callable $first)
    {
    }
}

final class PluginStub2 implements Plugin
{
    public function handleRequest(RequestInterface $request, callable $next, callable $first)
    {
    }
}
