<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Client\Common\Plugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use Http\Promise\Promise;
use Jean85\PrettyVersions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\RequestIntegration;
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
        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    public function testNullTransportIsUsedWhenNoServerIsConfigured(): void
    {
        $clientBuilder = new ClientBuilder();

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertInstanceOf(NullTransport::class, $transport);
    }

    public function testSetUriFactory(): void
    {
        /** @var UriFactory|MockObject $uriFactory */
        $uriFactory = $this->createMock(UriFactory::class);

        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setUriFactory($uriFactory);

        $this->assertAttributeSame($uriFactory, 'uriFactory', $clientBuilder);
    }

    public function testSetMessageFactory(): void
    {
        /** @var MessageFactory|MockObject $messageFactory */
        $messageFactory = $this->createMock(MessageFactory::class);

        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setMessageFactory($messageFactory);

        $this->assertAttributeSame($messageFactory, 'messageFactory', $clientBuilder);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertAttributeSame($messageFactory, 'requestFactory', $transport);
    }

    public function testSetTransport(): void
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setTransport($transport);

        $this->assertAttributeSame($transport, 'transport', $clientBuilder);
        $this->assertAttributeSame($transport, 'transport', $clientBuilder->getClient());
    }

    public function testSetHttpClient(): void
    {
        /** @var HttpAsyncClient|MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClient::class);

        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $clientBuilder->setHttpClient($httpClient);

        $this->assertAttributeSame($httpClient, 'httpClient', $clientBuilder);

        $transport = $this->getObjectAttribute($clientBuilder->getClient(), 'transport');

        $this->assertAttributeSame($httpClient, 'client', $this->getObjectAttribute($transport, 'httpClient'));
    }

    public function testAddHttpClientPlugin(): void
    {
        /** @var Plugin|MockObject $plugin */
        $plugin = $this->createMock(Plugin::class);

        $clientBuilder = new ClientBuilder();
        $clientBuilder->addHttpClientPlugin($plugin);

        $plugins = $this->getObjectAttribute($clientBuilder, 'httpClientPlugins');

        $this->assertCount(1, $plugins);
        $this->assertSame($plugin, $plugins[0]);
    }

    public function testRemoveHttpClientPlugin(): void
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

    public function testGetClient(): void
    {
        $clientBuilder = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/sentry/1']);
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
     * @dataProvider integrationsAreAddedToClientCorrectlyDataProvider
     */
    public function testIntegrationsAreAddedToClientCorrectly(bool $defaultIntegrations, array $integrations, array $expectedIntegrations): void
    {
        $options = new Options();
        $options->setDefaultIntegrations($defaultIntegrations);
        $options->setIntegrations($integrations);

        $clientBuilder = new ClientBuilder($options);
        $client = $clientBuilder->getClient();

        $actualIntegrationsClassNames = array_map('\get_class', $client->getOptions()->getIntegrations());

        $this->assertEquals($expectedIntegrations, $actualIntegrationsClassNames, '', 0, 10, true);
    }

    public function integrationsAreAddedToClientCorrectlyDataProvider(): array
    {
        return [
            [
                false,
                [],
                [],
            ],
            [
                false,
                [new StubIntegration()],
                [StubIntegration::class],
            ],
            [
                true,
                [],
                [
                    ErrorListenerIntegration::class,
                    ExceptionListenerIntegration::class,
                    RequestIntegration::class,
                ],
            ],
            [
                true,
                [new StubIntegration()],
                [
                    ErrorListenerIntegration::class,
                    ExceptionListenerIntegration::class,
                    RequestIntegration::class,
                    StubIntegration::class,
                ],
            ],
        ];
    }

    public function testClientBuilderFallbacksToDefaultSdkIdentifierAndVersion(): void
    {
        $callbackCalled = false;
        $expectedVersion = PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();

        $options = new Options();
        $options->setBeforeSendCallback(function (Event $event) use ($expectedVersion, &$callbackCalled) {
            $callbackCalled = true;

            $this->assertSame(Client::SDK_IDENTIFIER, $event->getSdkIdentifier());
            $this->assertSame($expectedVersion, $event->getSdkVersion());

            return null;
        });

        (new ClientBuilder($options))->getClient()->captureMessage('test');

        $this->assertTrue($callbackCalled, 'Callback not invoked, no assertions performed');
    }

    public function testClientBuilderSetsSdkIdentifierAndVersion(): void
    {
        $callbackCalled = false;

        $options = new Options();
        $options->setBeforeSendCallback(function (Event $event) use (&$callbackCalled) {
            $callbackCalled = true;

            $this->assertSame('sentry.test', $event->getSdkIdentifier());
            $this->assertSame('1.2.3-test', $event->getSdkVersion());

            return null;
        });

        (new ClientBuilder($options))
            ->setSdkIdentifier('sentry.test')
            ->setSdkVersion('1.2.3-test')
            ->getClient()
            ->captureMessage('test');

        $this->assertTrue($callbackCalled, 'Callback not invoked, no assertions performed');
    }

    /**
     * @dataProvider getClientTogglesCompressionPluginInHttpClientDataProvider
     */
    public function testGetClientTogglesCompressionPluginInHttpClient(bool $enabled): void
    {
        $options = new Options(['dsn' => 'http://public:secret@example.com/sentry/1']);
        $options->setEnableCompression($enabled);
        $builder = new ClientBuilder($options);
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

    public function testCreateWithNoOptionsIsTheSameAsDefaultOptions(): void
    {
        $this->assertEquals(
            new ClientBuilder(new Options()),
            ClientBuilder::create([])
        );
    }
}

final class StubIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
    }
}

final class PluginStub1 implements Plugin
{
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
    }
}

final class PluginStub2 implements Plugin
{
    public function handleRequest(RequestInterface $request, callable $next, callable $first): Promise
    {
    }
}
