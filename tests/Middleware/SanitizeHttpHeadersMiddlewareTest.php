<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Configuration;
use Sentry\Event;
use Sentry\Middleware\ProcessorMiddlewareInterface;
use Sentry\Middleware\SanitizeHttpHeadersMiddleware;

class SanitizeHttpHeadersMiddlewareTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($inputData, $expectedData)
    {
        $event = new Event(new Configuration());
        $event->setRequest($inputData);
        $request = $this->createMock(ServerRequestInterface::class);

        $middleware = new SanitizeHttpHeadersMiddleware([
            'sanitize_http_headers' => ['User-Defined-Header'],
        ]);

        $this->invokeMiddleware($middleware, $event, $request, $expectedData);
    }

    protected function invokeMiddleware(
        ProcessorMiddlewareInterface $middleware,
        Event $event,
        ServerRequestInterface $request = null,
        array $expectedData = []
    ) {
        $callbackInvoked = false;
        $callback = function (
            Event $eventArg,
            ServerRequestInterface $passedRequest = null,
            $exception = null,
            array $payload = []
        ) use ($request, $expectedData, &$callbackInvoked) {
            $this->assertArraySubset($expectedData, $eventArg->getRequest());
            $this->assertSame($request, $passedRequest);

            $callbackInvoked = true;
        };

        $middleware($event, $callback, $request);

        $this->assertTrue($callbackInvoked, 'Next middleware was not invoked');
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'headers' => [
                        'Authorization' => 'foo',
                        'AnotherHeader' => 'bar',
                        'User-Defined-Header' => 'baz',
                    ],
                ],
                [
                    'headers' => [
                        'Authorization' => 'foo',
                        'AnotherHeader' => 'bar',
                        'User-Defined-Header' => SanitizeHttpHeadersMiddleware::STRING_MASK,
                    ],
                ],
            ],
        ];
    }
}
