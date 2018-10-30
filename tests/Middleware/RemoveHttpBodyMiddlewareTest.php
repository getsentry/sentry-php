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

use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\RemoveHttpBodyMiddleware;

class RemoveHttpBodyMiddlewareTest extends MiddlewareTestCase
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
        $event = new Event($this->client->getOptions());
        $event->setRequest($inputData);

        $middleware = new RemoveHttpBodyMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertArraySubset($expectedData, $returnedEvent->getRequest());
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
