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
use Raven\Middleware\SanitizeHttpBodyMiddleware;

class SanitizeHttpBodyMiddlewareTest extends TestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var SanitizeHttpBodyMiddleware|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $middleware;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->middleware = new SanitizeHttpBodyMiddleware();
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

        $middleware = new SanitizeHttpBodyMiddleware();
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
                    'data' => SanitizeHttpBodyMiddleware::STRING_MASK,
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
                    'data' => SanitizeHttpBodyMiddleware::STRING_MASK,
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
                    'data' => SanitizeHttpBodyMiddleware::STRING_MASK,
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
                    'data' => SanitizeHttpBodyMiddleware::STRING_MASK,
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
