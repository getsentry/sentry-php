<?php

namespace Raven\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\PostBodyMiddleware;
use Zend\Diactoros\ServerRequest;

class PostBodyMiddlewareTest extends TestCase
{
    public function testInvokeWithNoRequest()
    {
        $event = new Event($this->prophesize(Configuration::class)->reveal());

        $invocationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invocationCount) {
            $this->assertSame($event, $eventArg);
            $this->assertArrayNotHasKey('data', $event->getRequest());

            ++$invocationCount;
        };

        $middleware = new PostBodyMiddleware();
        $middleware($event, $callback);

        $this->assertEquals(1, $invocationCount);
    }

    public function testInvokeWithNoBody()
    {
        $event = new Event($this->prophesize(Configuration::class)->reveal());
        $request = $this->createRequestWithBody(null);

        $invocationCount = 0;
        $callback = function (Event $eventArg, ServerRequestInterface $requestArg) use ($event, $request, &$invocationCount) {
            $this->assertSame($request, $requestArg);
            $this->assertSame($event, $eventArg);
            $this->assertArrayNotHasKey('data', $event->getRequest());

            ++$invocationCount;
        };

        $middleware = new PostBodyMiddleware();
        $middleware($event, $callback, $request);

        $this->assertEquals(1, $invocationCount);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $requestData, array $expectedValue)
    {
        $event = new Event($this->prophesize(Configuration::class)->reveal());
        $request = $this->createRequestWithBody($requestData['body']);

        $invocationCount = 0;
        $callback = function (Event $eventArg, ServerRequestInterface $requestArg) use ($request, $expectedValue, &$invocationCount) {
            $this->assertSame($request, $requestArg);
            $this->assertEquals($expectedValue, $eventArg->getRequest());

            ++$invocationCount;
        };

        $middleware = new PostBodyMiddleware();
        $middleware($event, $callback, $request);

        $this->assertEquals(1, $invocationCount);
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'body' => 'some-data',
                ],
                [
                    'data' => 'some-data',
                ],
            ],
        ];
    }

    /**
     * @param string|null $body
     *
     * @return ServerRequest
     */
    private function createRequestWithBody($body)
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stringBody = (string) $body;
        $stream->__toString()
            ->willReturn($stringBody);
        $stream->getSize()
            ->willReturn(strlen($stringBody));

        return (new ServerRequest())->withBody($stream->reveal());
    }
}
