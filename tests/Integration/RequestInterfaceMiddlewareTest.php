<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Integration;

use Sentry\Event;
use Sentry\Integration\RequestIntegration;
use Sentry\Options;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

class RequestInterfaceMiddlewareTest extends MiddlewareTestCase
{
    public function testInvokeWithNoRequest()
    {
        $configuration = new Options();
        $event = new Event($configuration);

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use ($event, &$callbackInvoked) {
            $this->assertSame($event, $eventArg);

            $callbackInvoked = true;
        };

        $middleware = new RequestIntegration();
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked, 'Next middleware NOT invoked');
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $requestData, array $expectedValue)
    {
        $configuration = new Options();
        $event = new Event($configuration);

        $request = new ServerRequest();
        $request = $request->withUri(new Uri($requestData['uri']));
        $request = $request->withMethod($requestData['method']);
        $request = $request->withCookieParams($requestData['cookies']);

        foreach ($requestData['headers'] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $middleware = new RequestIntegration();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, $request);

        $this->assertEquals($expectedValue, $returnedEvent->getRequest());
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'uri' => 'http://www.example.com/foo',
                    'method' => 'GET',
                    'cookies' => [
                        'foo' => 'bar',
                    ],
                    'headers' => [],
                ],
                [
                    'url' => 'http://www.example.com/foo',
                    'method' => 'GET',
                    'cookies' => [
                        'foo' => 'bar',
                    ],
                    'headers' => [
                        'Host' => ['www.example.com'],
                    ],
                ],
            ],
            [
                [
                    'uri' => 'http://www.example.com:123/foo',
                    'method' => 'GET',
                    'cookies' => [],
                    'headers' => [],
                ],
                [
                    'url' => 'http://www.example.com:123/foo',
                    'method' => 'GET',
                    'cookies' => [],
                    'headers' => [
                        'Host' => ['www.example.com:123'],
                    ],
                ],
            ],
            [
                [
                    'uri' => 'http://www.example.com/foo?foo=bar&bar=baz',
                    'method' => 'GET',
                    'cookies' => [],
                    'headers' => [
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                ],
                [
                    'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                    'method' => 'GET',
                    'query_string' => 'foo=bar&bar=baz',
                    'cookies' => [],
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
