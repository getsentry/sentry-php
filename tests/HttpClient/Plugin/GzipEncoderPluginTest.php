<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Plugin;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Promise\Promise as PromiseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\HttpClient\Plugin\GzipEncoderPlugin;

/**
 * @requires extension zlib
 */
final class GzipEncoderPluginTest extends TestCase
{
    public function testHandleRequest(): void
    {
        $streamFactory = Psr17FactoryDiscovery::findStreamFactory();

        $plugin = new GzipEncoderPlugin($streamFactory);
        $expectedPromise = $this->createMock(PromiseInterface::class);
        $request = Psr17FactoryDiscovery::findRequestFactory()
            ->createRequest('POST', 'http://www.example.com')
            ->withBody($streamFactory->createStream('foo'));

        $this->assertSame('foo', (string) $request->getBody());
        $this->assertSame($expectedPromise, $plugin->handleRequest(
            $request,
            function (RequestInterface $requestArg) use ($expectedPromise): PromiseInterface {
                $this->assertSame('gzip', $requestArg->getHeaderLine('Content-Encoding'));
                $this->assertSame(gzcompress('foo', -1, \ZLIB_ENCODING_GZIP), (string) $requestArg->getBody());

                return $expectedPromise;
            },
            static function (): void {}
        ));
    }
}
