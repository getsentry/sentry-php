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

use Sentry\Configuration;
use Sentry\Event;
use Sentry\Middleware\MessageInterfaceMiddleware;

class MessageInterfaceMiddlewareTest extends MiddlewareTestCase
{
    public function testInvokeWithoutMessage()
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $middleware = new MessageInterfaceMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertSame($event, $returnedEvent);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $payload)
    {
        $configuration = new Configuration();
        $event = new Event($configuration);

        $middleware = new MessageInterfaceMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, null, null, $payload);

        $this->assertEquals($payload['message'], $returnedEvent->getMessage());
        $this->assertEquals($payload['message_params'], $returnedEvent->getMessageParams());
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
