<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Mock\Client as HttpMockClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\Options;

final class HttpClientFactoryTest extends TestCase
{
    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(bool $isCompressionEnabled, string $expectedRequestBody): void
    {
        $mockHttpClient = new HttpMockClient();
        $httpClientFactory = new HttpClientFactory(
            UriFactoryDiscovery::find(),
            MessageFactoryDiscovery::find(),
            StreamFactoryDiscovery::find(),
            $mockHttpClient,
            'sentry.php.test',
            '1.2.3'
        );

        $httpClient = $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'default_integrations' => false,
            'enable_compression' => $isCompressionEnabled,
        ]));

        $httpClient->sendAsyncRequest(MessageFactoryDiscovery::find()->createRequest('POST', 'http://example.com/sentry/foo', [], 'foo bar'));

        /** @var RequestInterface|bool $httpRequest */
        $httpRequest = $mockHttpClient->getLastRequest();

        $this->assertInstanceOf(RequestInterface::class, $httpRequest);
        $this->assertSame('http://example.com/sentry/foo', (string) $httpRequest->getUri());
        $this->assertSame('sentry.php.test/1.2.3', $httpRequest->getHeaderLine('User-Agent'));
        $this->assertSame('Sentry sentry_version=7, sentry_client=sentry.php.test/1.2.3, sentry_key=public', $httpRequest->getHeaderLine('X-Sentry-Auth'));
        $this->assertSame($expectedRequestBody, (string) $httpRequest->getBody());
    }

    public function createDataProvider(): \Generator
    {
        yield [
            false,
            'foo bar',
        ];

        yield [
            true,
            gzcompress('foo bar', -1, ZLIB_ENCODING_GZIP),
        ];
    }

    public function testCreateThrowsIfDsnOptionIsNotConfigured(): void
    {
        $httpClientFactory = new HttpClientFactory(
            UriFactoryDiscovery::find(),
            MessageFactoryDiscovery::find(),
            StreamFactoryDiscovery::find(),
            null,
            'sentry.php.test',
            '1.2.3'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create an HTTP client without the Sentry DSN set in the options.');

        $httpClientFactory->create(new Options(['default_integrations' => false]));
    }

    public function testCreateThrowsIfHttpProxyOptionIsUsedWithCustomHttpClient(): void
    {
        $httpClientFactory = new HttpClientFactory(
            UriFactoryDiscovery::find(),
            MessageFactoryDiscovery::find(),
            StreamFactoryDiscovery::find(),
            $this->createMock(HttpAsyncClientInterface::class),
            'sentry.php.test',
            '1.2.3'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "http_proxy" option does not work together with a custom HTTP client.');

        $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'default_integrations' => false,
            'http_proxy' => 'http://example.com',
        ]));
    }
}
