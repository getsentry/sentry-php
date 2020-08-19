<?php

declare(strict_types=1);

namespace Sentry\Tests\Transport;

use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\HttpClient\HttpClientFactoryInterface;
use Sentry\Options;
use Sentry\Transport\DefaultTransportFactory;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\NullTransport;

final class DefaultTransportFactoryTest extends TestCase
{
    public function testCreateReturnsNullTransportWhenDsnOptionIsNotConfigured(): void
    {
        $factory = new DefaultTransportFactory(
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findRequestFactory(),
            $this->createMock(HttpClientFactoryInterface::class)
        );

        $this->assertInstanceOf(NullTransport::class, $factory->create(new Options()));
    }

    public function testCreateReturnsHttpTransportWhenDsnOptionIsConfigured(): void
    {
        $options = new Options(['dsn' => 'http://public@example.com/sentry/1']);

        /** @var HttpClientFactoryInterface&MockObject $httpClientFactory */
        $httpClientFactory = $this->createMock(HttpClientFactoryInterface::class);
        $httpClientFactory->expects($this->once())
            ->method('create')
            ->with($options)
            ->willReturn($this->createMock(HttpAsyncClientInterface::class));

        $factory = new DefaultTransportFactory(
            Psr17FactoryDiscovery::findStreamFactory(),
            Psr17FactoryDiscovery::findRequestFactory(),
            $httpClientFactory
        );

        $this->assertInstanceOf(HttpTransport::class, $factory->create($options));
    }
}
