<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use FriendsOfPHP\WellKnownImplementations\WellKnownPsr17Factory;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Mock\Client as HttpMockClient;
use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\Options;

final class HttpClientFactoryTest extends TestCase
{
    /**
     * @requires extension zlib
     * @dataProvider createDataProvider
     */
    public function testCreate(bool $isCompressionEnabled, string $expectedRequestBody): void
    {
        $psr17Factory = new WellKnownPsr17Factory();

        $mockHttpClient = new HttpMockClient();
        $httpClientFactory = new HttpClientFactory(
            null,
            null,
            $psr17Factory,
            $mockHttpClient,
            'sentry.php.test',
            '1.2.3'
        );

        $httpClient = $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'default_integrations' => false,
            'enable_compression' => $isCompressionEnabled,
        ]));

        $request = $psr17Factory
            ->createRequest('POST', 'http://example.com/sentry/foo')
            ->withBody($psr17Factory->createStream('foo bar'));

        $httpClient->sendAsyncRequest($request);

        $httpRequest = $mockHttpClient->getLastRequest();

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
            gzcompress('foo bar', -1, \ZLIB_ENCODING_GZIP),
        ];
    }

    public function testCreateThrowsIfDsnOptionIsNotConfigured(): void
    {
        $httpClientFactory = new HttpClientFactory(
            null,
            null,
            new WellKnownPsr17Factory(),
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
            null,
            null,
            new WellKnownPsr17Factory(),
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
