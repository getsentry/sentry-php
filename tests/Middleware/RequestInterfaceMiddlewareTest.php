<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\RequestInterfaceMiddleware;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

class RequestInterfaceMiddlewareTest extends TestCase
{
    private static $inputData;

    public function testInvokeWithNoRequest()
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertSame($event, $eventArg);

            ++$invokationCount;
        };

        $middleware = new RequestInterfaceMiddleware();
        $middleware($event, $callback);

        $this->assertEquals(1, $invokationCount);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $requestData, array $expectedValue, $inputData = null)
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $request = new ServerRequest();
        $request = $request->withUri(new Uri($requestData['uri']));
        $request = $request->withMethod($requestData['method']);
        $request = $request->withCookieParams($requestData['cookies']);

        foreach ($requestData['headers'] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $invokationCount = 0;
        $callback = function (Event $eventArg, ServerRequestInterface $requestArg) use ($event, $request, $expectedValue, &$invokationCount) {
            $this->assertSame($request, $requestArg);
            $this->assertEquals($expectedValue, $eventArg->getRequest());

            ++$invokationCount;
        };

        $middleware = new RequestInterfaceMiddleware();
        if ($inputData) {
            $this->mockReadFromPhpInput($inputData);
        }

        $middleware($event, $callback, $request);

        $this->assertEquals(1, $invokationCount);
    }

    public function invokeDataProvider()
    {
        $validData = ['json_test' => 'json_data'];
        $invalidData = '{"binary_json":"' . pack('NA3CC', 3, 'aBc', 0x0D, 0x0A) . '"}';

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
            [
                [
                    'uri' => 'http://www.example.com/foo',
                    'method' => 'POST',
                    'cookies' => [],
                    'headers' => [
                        'Content-Type' => ['application/json'],
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                ],
                [
                    'url' => 'http://www.example.com/foo',
                    'method' => 'POST',
                    'cookies' => [],
                    'data' => $validData,
                    'headers' => [
                        'Content-Type' => ['application/json'],
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                    'env' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                    ],
                ],
                json_encode($validData),
            ],
            [
                [
                    'uri' => 'http://www.example.com/foo',
                    'method' => 'POST',
                    'cookies' => [],
                    'headers' => [
                        'Content-Type' => ['application/json'],
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                ],
                [
                    'url' => 'http://www.example.com/foo',
                    'method' => 'POST',
                    'cookies' => [],
                    'data' => null,
                    'headers' => [
                        'Content-Type' => ['application/json'],
                        'Host' => ['www.example.com'],
                        'REMOTE_ADDR' => ['127.0.0.1'],
                    ],
                    'env' => [
                        'REMOTE_ADDR' => '127.0.0.1',
                    ],
                ],
                $invalidData,
            ],
        ];
    }

    /**
     * @return string
     */
    public static function getInputData()
    {
        return self::$inputData;
    }

    /**
     * @param string $inputData
     */
    private function mockReadFromPhpInput($inputData)
    {
        self::$inputData = $inputData;
        if (function_exists('Raven\Middleware\file_get_contents')) {
            return;
        }

        $self = \get_class($this);

        eval(<<<EOPHP
namespace Raven\Middleware;

function file_get_contents()
{
    return \\$self::getInputData();
}

EOPHP
        );
    }
}
