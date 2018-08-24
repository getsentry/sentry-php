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
use Raven\Middleware\UserInterfaceMiddleware;
use Zend\Diactoros\ServerRequest;

class UserInterfaceMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $event = new Event(new Configuration());
        $event->setUserContext(['foo' => 'bar']);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertSame($event, $eventArg);

            ++$invokationCount;
        };

        $middleware = new UserInterfaceMiddleware();
        $middleware($event, $callback);

        $this->assertEquals(1, $invokationCount);
    }

    public function testInvokeWithRequest()
    {
        $event = new Event(new Configuration());
        $event->setUserContext(['foo' => 'bar']);

        $request = new ServerRequest();
        $request = $request->withHeader('REMOTE_ADDR', '127.0.0.1');

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            $this->assertEquals(['ip_address' => '127.0.0.1', 'foo' => 'bar'], $eventArg->getUserContext());

            $callbackInvoked = true;
        };

        $middleware = new UserInterfaceMiddleware();
        $middleware($event, $callback, $request);

        $this->assertTrue($callbackInvoked);
    }
}
