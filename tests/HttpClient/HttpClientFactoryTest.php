<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use GuzzleHttp\RequestOptions;
use Http\Adapter\Guzzle6\Client as Guzzle6HttpClient;
use Http\Adapter\Guzzle7\Client as Guzzle7HttpClient;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\DecoderPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Curl\Client as CurlHttpClient;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\ClassDiscovery;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Mock\Client as HttpMockClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\HttpClient\Authentication\SentryAuthentication;
use Sentry\HttpClient\HttpClientFactory;
use Sentry\HttpClient\Plugin\GzipEncoderPlugin;
use Sentry\Options;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Component\HttpClient\HttplugClient as SymfonyHttplugClient;

final class HttpClientFactoryTest extends TestCase
{
    /**
     * @requires extension zlib
     * @dataProvider createDataProvider
     */
    public function testCreate(bool $isCompressionEnabled, string $expectedRequestBody): void
    {
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $mockHttpClient = new HttpMockClient();
        $httpClientFactory = new HttpClientFactory(
            Psr17FactoryDiscovery::findUrlFactory(),
            Psr17FactoryDiscovery::findResponseFactory(),
            $streamFactory,
            $mockHttpClient,
            'sentry.php.test',
            '1.2.3'
        );

        $httpClient = $httpClientFactory->create(new Options([
            'dsn' => 'http://public@example.com/sentry/1',
            'default_integrations' => false,
            'enable_compression' => $isCompressionEnabled,
        ]));

        $request = Psr17FactoryDiscovery::findRequestFactory()
            ->createRequest('POST', 'http://example.com/sentry/foo')
            ->withBody($streamFactory->createStream('foo bar'));

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
            Psr17FactoryDiscovery::findUrlFactory(),
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
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
            Psr17FactoryDiscovery::findUrlFactory(),
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
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

    /**
     * @runInSeparateProcess
     */
    public function testResolveClientWithSymfonyClient()
    {
        new SymfonyHttplugClient(SymfonyHttpClient::create([]));
        $this->assertClientInstance(SymfonyHttplugClient::class);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResolveClientWithGuzzle6Client()
    {
        if (!class_exists(Guzzle6HttpClient::class)) {
            self::markTestSkipped('This test requires Guzzle 6 adapter');
        }
        Guzzle6HttpClient::createWithConfig([]);
        class_exists(RequestOptions::class);
        $this->assertClientInstance(Guzzle6HttpClient::class);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResolveClientWithGuzzle7Client()
    {
        if (!class_exists(Guzzle7HttpClient::class)) {
            self::markTestSkipped('This test requires Guzzle 7 adapter');
        }

        self::markTestIncomplete('Factory does not support Guzzle 7 adapter');
        Guzzle7HttpClient::createWithConfig([]);
        class_exists(RequestOptions::class);
        $this->assertClientInstance(Guzzle7HttpClient::class);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResolveClientWithCurlClient()
    {
        new CurlHttpClient();
        $this->assertClientInstance(CurlHttpClient::class);
    }

    /**
     * @runInSeparateProcess
     */
    public function testResolveClientWithDefaultClient()
    {
        class_exists(HttpAsyncClientDiscovery::class);
        $mock = $this->createMock(HttpAsyncClientInterface::class);
        $reflectedClass = new \ReflectionClass(ClassDiscovery::class);
        $method = $reflectedClass->getMethod('storeInCache');
        $method->setAccessible(true);
        $method->invokeArgs(null, [HttpAsyncClientInterface::class, ['class' => \get_class($mock)]]);
        $this->assertClientInstance(\get_class($mock));
    }

    private function assertClientInstance(string $expectedClientClass)
    {
        $httpClientFactory = new HttpClientFactory(
            Psr17FactoryDiscovery::findUrlFactory(),
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            null,
            'sentry.php.test',
            '1.2.3'
        );
        class_exists(HeaderSetPlugin::class);
        class_exists(AuthenticationPlugin::class);
        class_exists(SentryAuthentication::class);
        class_exists(RetryPlugin::class);
        class_exists(ErrorPlugin::class);
        class_exists(GzipEncoderPlugin::class);
        class_exists(DecoderPlugin::class);
        class_exists(PluginClient::class);
        class_exists(RequestInterface::class);
        $options = new Options(['dsn' => 'http://public@example.com/sentry/1']);
        $autoloaders = spl_autoload_functions();
        array_map('spl_autoload_unregister', $autoloaders);

        try {
            $client = $httpClientFactory->create($options);
        } catch (\Throwable $exception) {
            throw $exception;
        } finally {
            array_map('spl_autoload_register', $autoloaders);
        }
        $reflectedClient = new \ReflectionClass($client);
        $clientProperty = $reflectedClient->getProperty('client');
        $clientProperty->setAccessible(true);
        self::assertInstanceOf($expectedClientClass, $clientProperty->getValue($client));
    }
}
