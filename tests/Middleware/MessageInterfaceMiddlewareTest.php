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
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\MessageInterfaceMiddleware;

class MessageInterfaceMiddlewareTest extends TestCase
{
    public function testInvokeWithoutMessage()
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertSame($event, $eventArg);

            ++$invokationCount;
        };

        $middleware = new MessageInterfaceMiddleware();
        $middleware($event, $callback);

        $this->assertEquals(1, $invokationCount);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke($payload)
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use ($payload, &$callbackInvoked) {
            $this->assertEquals($payload['message'], $eventArg->getMessage());
            $this->assertEquals($payload['message_params'], $eventArg->getMessageParams());

            $callbackInvoked = true;
        };

        $middleware = new MessageInterfaceMiddleware();
        $middleware($event, $callback, null, null, $payload);

        $this->assertTrue($callbackInvoked);
    }

    public function invokeDataProvider()
    {
        return [
            [
                [
                    'message' => 'foo %s',
                    'message_params' => [],
                ],
            ],
            [
                [
                    'message' => 'foo %s',
                    'message_params' => ['bar'],
                ],
            ],
        ];
    }
}
