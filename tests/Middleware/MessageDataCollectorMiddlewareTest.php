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
use Raven\Configuration;
use Raven\Event;
use Raven\Middleware\MessageDataCollectorMiddleware;

class MessageDataCollectorMiddlewareTest extends TestCase
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

        $middleware = new MessageDataCollectorMiddleware();
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

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, $payload, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);

            $this->assertEquals($payload['message'], $eventArg->getMessage());
            $this->assertEquals($payload['message_params'], $eventArg->getMessageParams());

            ++$invokationCount;
        };

        $middleware = new MessageDataCollectorMiddleware();
        $middleware($event, $callback, null, null, $payload);

        $this->assertEquals(1, $invokationCount);
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
