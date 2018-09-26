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
use Raven\ClientBuilder;
use Raven\ClientInterface;
use Raven\Event;
use Raven\Middleware\RemoveHttpBodyMiddleware;

class RemoveHttpBodyMiddlewareTest extends TestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var RemoveHttpBodyMiddleware|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $middleware;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->middleware = new RemoveHttpBodyMiddleware();
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($inputData, $expectedData)
    {
        $event = new Event($this->client->getConfig());
        $event->setRequest($inputData);

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use ($expectedData, &$callbackInvoked) {
            $this->assertArraySubset($expectedData, $eventArg->getRequest());

            $callbackInvoked = true;
        };

        $middleware = new RemoveHttpBodyMiddleware();
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'method' => 'POST',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyMiddleware::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'PUT',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyMiddleware::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'PATCH',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyMiddleware::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'DELETE',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => RemoveHttpBodyMiddleware::STRING_MASK,
                ],
            ],
            [
                [
                    'method' => 'GET',
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
                [
                    'data' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ];
    }
}
