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

use PHPUnit\Framework\TestCase;
use Sentry\Configuration;
use Sentry\Event;
use Sentry\Middleware\UserInterfaceMiddleware;
use Zend\Diactoros\ServerRequest;

class UserInterfaceMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $event = new Event(new Configuration());
        $event->getUserContext()->setData(['foo' => 'bar']);

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            $this->assertArrayNotHasKey('ip_address', $eventArg->getUserContext());

            $callbackInvoked = true;
        };

        $middleware = new UserInterfaceMiddleware();
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }

    public function testInvokeWithRequest()
    {
        $event = new Event(new Configuration());
        $event->getUserContext()->setData(['foo' => 'bar']);

        $request = new ServerRequest();
        $request = $request->withHeader('REMOTE_ADDR', '127.0.0.1');

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            $this->assertEquals(['ip_address' => '127.0.0.1', 'foo' => 'bar'], $eventArg->getUserContext()->toArray());

            $callbackInvoked = true;
        };

        $middleware = new UserInterfaceMiddleware();
        $middleware($event, $callback, $request);

        $this->assertTrue($callbackInvoked);
    }
}
