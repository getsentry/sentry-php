<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient;

use Http\Client\Common\Plugin as HttpClientPluginInterface;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Promise\Promise as PromiseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\HttpClient\HttpClientFactoryInterface;
use Sentry\HttpClient\PluggableHttpClientFactory;
use Sentry\Options;

/**
 * @group legacy
 */
final class PluggableHttpClientFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        /** @var HttpAsyncClientInterface&MockObject $wrappedHttpClient */
        $wrappedHttpClient = $this->createMock(HttpAsyncClientInterface::class);
        $wrappedHttpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($this->createMock(PromiseInterface::class));

        /** @var HttpClientPluginInterface&MockObject $httpClientPlugin */
        $httpClientPlugin = $this->createMock(HttpClientPluginInterface::class);
        $httpClientPlugin->expects($this->once())
            ->method('handleRequest')
            ->willReturnCallback(static function (RequestInterface $request, callable $next): PromiseInterface {
                return $next($request);
            });

        $httpClientFactory = new class($wrappedHttpClient) implements HttpClientFactoryInterface {
            private $httpClient;

            public function __construct(HttpAsyncClientInterface $httpClient)
            {
                $this->httpClient = $httpClient;
            }

            public function create(Options $options): HttpAsyncClientInterface
            {
                return $this->httpClient;
            }
        };

        $httpClientFactory = new PluggableHttpClientFactory($httpClientFactory, [$httpClientPlugin]);
        $httpClient = $httpClientFactory->create(new Options(['default_integrations' => false]));

        $httpClient->sendAsyncRequest($this->createMock(RequestInterface::class));
    }
}
