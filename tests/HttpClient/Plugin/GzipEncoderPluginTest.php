<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Plugin;

use FriendsOfPHP\WellKnownImplementations\WellKnownPsr17Factory;
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
        $psr17Factory = new WellKnownPsr17Factory();
        $plugin = new GzipEncoderPlugin($psr17Factory);
        $expectedPromise = $this->createMock(PromiseInterface::class);
        $request = $psr17Factory
            ->createRequest('POST', 'http://www.example.com')
            ->withBody($psr17Factory->createStream('foo'));

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
