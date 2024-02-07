<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\HttpClient\HttpClient;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

final class ClientBuilderTest extends TestCase
{
    public function testGetOptions()
    {
        $options = new Options();
        $clientBuilder = new ClientBuilder($options);

        $this->assertSame($options, $clientBuilder->getOptions());
    }

    public function testClientBuilderFallbacksToDefaultSdkIdentifierAndVersion(): void
    {
        $callbackCalled = false;

        $options = new Options();
        $options->setBeforeSendCallback(function (Event $event) use (&$callbackCalled) {
            $callbackCalled = true;

            $this->assertSame(Client::SDK_IDENTIFIER, $event->getSdkIdentifier());
            $this->assertSame(Client::SDK_VERSION, $event->getSdkVersion());

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

    public function testDefaultHttpClientAndTransport(): void
    {
        $options = new Options();
        $clientBuilder = new ClientBuilder($options);

        $this->assertInstanceOf(HttpClient::class, $clientBuilder->getHttpClient());
        $this->assertInstanceOf(HttpTransport::class, $clientBuilder->getTransport());
    }

    public function testSettingCustomLogger(): void
    {
        $logger = new CustomLogger();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->setLogger($logger);

        $this->assertSame($logger, $clientBuilder->getLogger());

        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($logger, $client->getLogger());
    }

    public function testSettingCustomLoggerFromOptions(): void
    {
        $logger = new CustomLogger();

        $options = new Options([
            'logger' => $logger,
        ]);
        $clientBuilder = new ClientBuilder($options);

        $this->assertSame($logger, $clientBuilder->getLogger());

        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($logger, $client->getLogger());
    }

    public function testSettingCustomHttpClient(): void
    {
        $httpClient = new CustomHttpClient();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->setHttpClient($httpClient);

        $this->assertSame($httpClient, $clientBuilder->getHttpClient());

        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);

        $transport = $client->getTransport();

        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertSame($httpClient, $transport->getHttpClient());
    }

    public function testSettingCustomHttpClientFromOptions(): void
    {
        $httpClient = new CustomHttpClient();

        $options = new Options([
            'http_client' => $httpClient,
        ]);
        $clientBuilder = new ClientBuilder($options);

        $this->assertSame($httpClient, $clientBuilder->getHttpClient());

        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);

        $transport = $client->getTransport();

        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertSame($httpClient, $transport->getHttpClient());
    }

    public function testSettingCustomTransport(): void
    {
        $transport = new CustomTransport();

        $clientBuilder = new ClientBuilder();
        $clientBuilder->setTransport($transport);

        $this->assertSame($transport, $clientBuilder->getTransport());

        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($transport, $client->getTransport());
    }

    public function testSettingCustomTransportFromOptions(): void
    {
        $transport = new CustomTransport();

        $options = new Options([
            'transport' => $transport,
        ]);
        $clientBuilder = new ClientBuilder($options);

        $this->assertSame($transport, $clientBuilder->getTransport());

        $client = $clientBuilder->getClient();

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($transport, $client->getTransport());
    }
}

final class StubIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
    }
}

final class CustomHttpClient implements HttpClientInterface
{
    public function sendRequest(Request $request, Options $options): Response
    {
        return new Response(0, [], '');
    }
}

final class CustomTransport implements TransportInterface
{
    public function send(Event $event): Result
    {
        return new Result(ResultStatus::success());
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}

final class CustomLogger extends AbstractLogger implements LoggerInterface
{
    public function log($level, $message, array $context = []): void
    {
        // noop
    }
}
