<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Breadcrumbs;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\RequestDataCollectorMiddleware;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

class RequestDataCollectorMiddlewareTest extends TestCase
{
    public function testInvokeWithNoRequest()
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertSame($event, $eventArg);

            ++$invokationCount;
        };

        $middleware = new RequestDataCollectorMiddleware();
        $middleware($event, $callback);

        $this->assertEquals(1, $invokationCount);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($requestData, $expectedValue)
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $request = new ServerRequest();
        $request = $request->withUri(new Uri($requestData['uri']));
        $request = $request->withMethod($requestData['method']);

        foreach ($requestData['headers'] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $invokationCount = 0;
        $callback = function (Event $eventArg, ServerRequestInterface $requestArg) use ($event, $request, $expectedValue, &$invokationCount) {
            $this->assertSame($request, $requestArg);
            $this->assertEquals($expectedValue, $eventArg->getRequest());

            ++$invokationCount;
        };

        $middleware = new RequestDataCollectorMiddleware();
        $middleware($event, $callback, $request);

        $this->assertEquals(1, $invokationCount);
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'uri' => 'http://www.example.com/foo',
                    'method' => 'GET',
                    'headers' => [],
                ],
                [
                    'url' => 'http://www.example.com/foo',
                    'method' => 'GET',
                    'headers' => [
                        'Host' => ['www.example.com'],
                    ],
                ],
            ],
            [
                [
                    'uri' => 'http://www.example.com/foo?foo=bar&bar=baz',
                    'method' => 'GET',
                    'headers' => [
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                ],
                [
                    'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                    'method' => 'GET',
                    'query_string' => 'foo=bar&bar=baz',
                    'headers' => [
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                    'env' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                    ],
                ],
            ],
        ];
    }
}
