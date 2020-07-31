<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Jean85\PrettyVersions;
use PHPUnit\Framework\TestCase;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
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
