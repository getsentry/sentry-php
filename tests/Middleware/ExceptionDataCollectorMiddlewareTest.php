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
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Middleware\ExceptionDataCollectorMiddleware;

class ExceptionDataCollectorMiddlewareTest extends TestCase
{
    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($exception, $clientConfig, $payload, $expectedResult)
    {
        $client = ClientBuilder::create($clientConfig)->getClient();
        $event = new Event($client->getConfig());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, $expectedResult, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertArraySubset($expectedResult, $eventArg->toArray());

            ++$invokationCount;
        };

        $middleware = new ExceptionDataCollectorMiddleware($client);
        $middleware($event, $callback, null, $exception, $payload);

        $this->assertEquals(1, $invokationCount);
    }

    public function invokeDataProvider()
    {
        return [
            [
                new \RuntimeException('foo'),
                [],
                [],
                [
                    'level' => Client::LEVEL_ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \RuntimeException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \ErrorException('foo', 0, E_USER_WARNING),
                [],
                [],
                [
                    'level' => Client::LEVEL_WARNING,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \ErrorException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \ErrorException('foo', 0, E_USER_WARNING),
                [],
                [
                    'level' => Client::LEVEL_INFO,
                ],
                [
                    'level' => Client::LEVEL_INFO,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \ErrorException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \BadMethodCallException('baz', 0, new \BadFunctionCallException('bar', 0, new \LogicException('foo', 0))),
                [
                    'excluded_exceptions' => [\BadMethodCallException::class],
                ],
                [],
                [
                    'level' => Client::LEVEL_ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \LogicException::class,
                                'value' => 'foo',
                            ],
                            [
                                'type' => \BadFunctionCallException::class,
                                'value' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
