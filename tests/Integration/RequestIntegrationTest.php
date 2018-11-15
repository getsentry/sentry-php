<?php

declare(strict_types=1);

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\Integration\RequestIntegration;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

final class RequestIntegrationTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $requestData, array $expectedValue): void
    {
        $event = new Event();

        $request = new ServerRequest();
        $request = $request->withCookieParams($requestData['cookies']);
        $request = $request->withUri(new Uri($requestData['uri']));
        $request = $request->withMethod($requestData['method']);

        foreach ($requestData['headers'] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->assertInstanceOf(ServerRequestInterface::class, $request);

        RequestIntegration::applyToEvent($event, $request);

        $this->assertEquals($expectedValue, $event->getRequest());
    }

    public function testInvokeWithRequestHavingIpAddress(): void
    {
        $event = new Event();
        $event->getUserContext()->setData(['foo' => 'bar']);

        $request = new ServerRequest();
        $request = $request->withHeader('REMOTE_ADDR', '127.0.0.1');
        $this->assertInstanceOf(ServerRequestInterface::class, $request);

        RequestIntegration::applyToEvent($event, $request);

        $this->assertEquals(['ip_address' => '127.0.0.1', 'foo' => 'bar'], $event->getUserContext()->toArray());
    }

    public function invokeDataProvider(): array
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
