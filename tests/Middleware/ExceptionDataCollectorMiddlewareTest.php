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
    public function testInvoke()
    {
        $client = ClientBuilder::create(['excluded_exceptions' => [\BadMethodCallException::class]])
            ->getClient();

        $event = new Event($client->getConfig());
        $exception = new \LogicException('foo', 0);
        $exception = new \BadFunctionCallException('bar', 0, $exception);
        $exception = new \BadMethodCallException('baz', 0, $exception);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertEquals(Client::LEVEL_ERROR, $eventArg->getLevel());

            $result = [
                [
                    'type' => \LogicException::class,
                    'value' => 'foo',
                ],
                [
                    'type' => \BadFunctionCallException::class,
                    'value' => 'bar',
                ],
            ];

            $this->assertArraySubset($result, $eventArg->getException());

            ++$invokationCount;
        };

        $middleware = new ExceptionDataCollectorMiddleware($client);
        $middleware($event, $callback, null, $exception);

        $this->assertEquals(1, $invokationCount);
    }

    public function testInvokeWithLevel()
    {
        $client = ClientBuilder::create()->getClient();

        $event = new Event($client->getConfig());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertEquals(Client::LEVEL_INFO, $eventArg->getLevel());

            ++$invokationCount;
        };

        $middleware = new ExceptionDataCollectorMiddleware($client);
        $middleware($event, $callback, null, null, ['level' => Client::LEVEL_INFO]);

        $this->assertEquals(1, $invokationCount);
    }
}
