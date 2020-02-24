<?php

declare(strict_types=1);

namespace Sentry\Tests\HttpClient\Plugin;

use Http\Discovery\MessageFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Promise\Promise as PromiseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sentry\HttpClient\Plugin\GzipEncoderPlugin;

final class GzipEncoderPluginTest extends TestCase
{
    /**
     * @requires extension zlib
     */
    public function testHandleRequest(): void
    {
        $plugin = new GzipEncoderPlugin();
        $nextCallableCalled = false;
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
                function (RequestInterface $requestArg) use (&$nextCallableCalled, $expectedPromise): PromiseInterface {
                    $nextCallableCalled = true;

                    $this->assertSame('gzip', $requestArg->getHeaderLine('Content-Encoding'));
                    $this->assertSame(gzcompress('foo', -1, ZLIB_ENCODING_GZIP), (string) $requestArg->getBody());

                    return $expectedPromise;
                },
                static function () {}
            )
        );

        $this->assertTrue($nextCallableCalled);
    }
}
