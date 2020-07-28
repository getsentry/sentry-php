<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Client\Common\Plugin as PluginInterface;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory as MessageFactoryInterface;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise as PromiseInterface;
use Jean85\PrettyVersions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\FlushableClientInterface;
use Sentry\Integration\ErrorListenerIntegration;
use Sentry\Integration\ExceptionListenerIntegration;
use Sentry\Integration\FatalErrorListenerIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\IntegrationInterface;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;
use Sentry\Transport\TransportInterface;

final class ClientBuilderTest extends TestCase
{
    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testHttpTransportIsUsedWhenServerIsConfigured(): void
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

    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testSetMessageFactory(): void
    {
        /** @var MessageFactoryInterface&MockObject $messageFactory */
        $messageFactory = $this->createMock(MessageFactoryInterface::class);
        $messageFactory->expects($this->once())
            ->method('createRequest')
            ->willReturn(MessageFactoryDiscovery::find()->createRequest('POST', 'http://www.example.com'));

        $client = ClientBuilder::create(['dsn' => 'http://public@example.com/sentry/1'])
            ->setMessageFactory($messageFactory)
            ->getClient();

        $client->captureMessage('foo');
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Method Sentry\ClientBuilder::setTransport() is deprecated since version 2.3 and will be removed in 3.0. Use the setTransportFactory() method instead.
     */
    public function testSetTransport(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn('ddb4a0b9ab1941bf92bd2520063663e3');

        $client = ClientBuilder::create(['dsn' => 'http://public@example.com/sentry/1'])
            ->setTransport($transport)
            ->getClient();

        $this->assertSame('ddb4a0b9ab1941bf92bd2520063663e3', $client->captureMessage('foo'));
    }

    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Delaying the sending of the events using the "Sentry\Transport\HttpTransport" class is deprecated since version 2.2 and will not work in 3.0.
     */
    public function testSetHttpClient(): void
    {
        /** @var HttpAsyncClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpAsyncClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn(new FulfilledPromise(true));

        /** @var FlushableClientInterface $client */
        $client = ClientBuilder::create(['dsn' => 'http://public@example.com/sentry/1'])
            ->setHttpClient($httpClient)
            ->getClient();

        $client->captureMessage('foo');
        $client->flush();
    }

    /**
     * @group legacy
     *
     * @expectedDeprecationMessage Method Sentry\ClientBuilder::addHttpClientPlugin() is deprecated since version 2.3 and will be removed in 3.0.
     */
    public function testAddHttpClientPlugin(): void
    {
        /** @var PluginInterface&MockObject $plugin */
        $plugin = $this->createMock(PluginInterface::class);
        $plugin->expects($this->once())
            ->method('handleRequest')
            ->willReturn(new FulfilledPromise(true));

        /** @var FlushableClientInterface $client */
        $client = ClientBuilder::create(['dsn' => 'http://public@example.com/sentry/1'])
            ->addHttpClientPlugin($plugin)
            ->getClient();

        $client->captureMessage('foo');
        $client->flush();
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Method Sentry\ClientBuilder::addHttpClientPlugin() is deprecated since version 2.3 and will be removed in 3.0.
     * @expectedDeprecation Method Sentry\ClientBuilder::removeHttpClientPlugin() is deprecated since version 2.3 and will be removed in 3.0.
     */
    public function testRemoveHttpClientPlugin(): void
    {
        $plugin = new class() implements PluginInterface {
            public function handleRequest(RequestInterface $request, callable $next, callable $first): PromiseInterface
            {
                return new FulfilledPromise(true);
            }
        };

        $plugin2 = new class() implements PluginInterface {
            public function handleRequest(RequestInterface $request, callable $next, callable $first): PromiseInterface
            {
                return new FulfilledPromise(true);
            }
        };

        /** @var FlushableClientInterface $client */
        $client = ClientBuilder::create()
            ->addHttpClientPlugin($plugin)
            ->addHttpClientPlugin($plugin)
            ->addHttpClientPlugin($plugin2)
            ->removeHttpClientPlugin(\get_class($plugin2))
            ->getClient();

        $client->captureMessage('foo');
        $client->flush();
    }

    /**
     * @dataProvider integrationsAreAddedToClientCorrectlyDataProvider
     */
    public function testIntegrationsAreAddedToClientCorrectly(bool $defaultIntegrations, array $integrations, array $expectedIntegrations): void
    {
        $options = new Options();
        $options->setDefaultIntegrations($defaultIntegrations);
        $options->setIntegrations($integrations);

        $client = (new ClientBuilder($options))->getClient();

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
                    FatalErrorListenerIntegration::class,
                    ExceptionListenerIntegration::class,
                    RequestIntegration::class,
                    TransactionIntegration::class,
                    FrameContextifierIntegration::class,
                ],
            ],
            [
                true,
                [new StubIntegration()],
                [
                    ErrorListenerIntegration::class,
                    FatalErrorListenerIntegration::class,
                    ExceptionListenerIntegration::class,
                    RequestIntegration::class,
                    TransactionIntegration::class,
                    FrameContextifierIntegration::class,
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
