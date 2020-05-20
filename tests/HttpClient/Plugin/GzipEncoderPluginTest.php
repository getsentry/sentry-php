<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Plugin;

use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Message\StreamFactory as HttplugStreamFactoryInterface;
use Http\Promise\Promise as PromiseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sentry\HttpClient\Plugin\GzipEncoderPlugin;

/**
 * @requires extension zlib
 */
final class GzipEncoderPluginTest extends TestCase
{
    /**
     * @dataProvider constructorThrowsIfArgumentsAreInvalidDataProvider
     */
    public function testConstructorThrowsIfArgumentsAreInvalid($streamFactory, bool $shouldThrowException): void
    {
        if ($shouldThrowException) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('The $streamFactory argument must be an instance of either the "Http\Message\StreamFactory" or the "Psr\Http\Message\StreamFactoryInterface" interface.');
        } else {
            $this->expectNotToPerformAssertions();
        }

        new GzipEncoderPlugin($streamFactory);
    }

    public function constructorThrowsIfArgumentsAreInvalidDataProvider(): \Generator
    {
        yield [
            'foo',
            true,
        ];

        yield [
            $this->createMock(StreamFactoryInterface::class),
            false,
        ];

        yield [
            $this->createMock(HttplugStreamFactoryInterface::class),
            false,
        ];
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation A PSR-17 stream factory is needed as argument of the constructor of the "Sentry\HttpClient\Plugin\GzipEncoderPlugin" class since version 2.1.3 and will be required in 3.0.
     */
    public function testConstructorThrowsDeprecationErrorIfNoStreamFactoryIsProvided(): void
    {
        new GzipEncoderPlugin();
    }

    public function testHandleRequest(): void
    {
        $plugin = new GzipEncoderPlugin(StreamFactoryDiscovery::find());
        $expectedPromise = $this->createMock(PromiseInterface::class);
        $request = MessageFactoryDiscovery::find()->createRequest(
            'POST',
            'http://www.local.host',
            [],
            'foo'
        );

        $this->assertSame('foo', (string) $request->getBody());
        $this->assertSame(
            $expectedPromise,
            $plugin->handleRequest(
                $request,
                function (RequestInterface $requestArg) use ($expectedPromise): PromiseInterface {
                    $this->assertSame('gzip', $requestArg->getHeaderLine('Content-Encoding'));
                    $this->assertSame(gzcompress('foo', -1, ZLIB_ENCODING_GZIP), (string) $requestArg->getBody());

                    return $expectedPromise;
                },
                static function (): void {}
            )
        );
    }
}
