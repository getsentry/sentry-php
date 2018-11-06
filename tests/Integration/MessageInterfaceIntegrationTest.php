<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Integration\MessageInterfaceMiddleware;

class MessageInterfaceIntegrationTest extends TestCase
{
    public function testInvokeWithoutMessage()
    {
        $event = new Event();

        $middleware = new MessageInterfaceMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertSame($event, $returnedEvent);
    }

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(array $payload)
    {
        $event = new Event();

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
