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
use Raven\Context;
use Raven\Event;
use Raven\Middleware\UserDataCollectorMiddleware;
use Zend\Diactoros\ServerRequest;

class UserDataCollectorMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $context = new Context();
        $context->mergeUserData(['foo' => 'bar']);

        $configuration = new Configuration();
        $event = new Event($configuration);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertEquals(['foo' => 'bar'], $eventArg->getUserContext());

            ++$invokationCount;
        };

        $middleware = new UserDataCollectorMiddleware($context);
        $middleware($event, $callback);

        $this->assertEquals(1, $invokationCount);
    }

    public function testInvokeWithRequest()
    {
        $context = new Context();
        $context->mergeUserData(['foo' => 'bar']);

        $request = new ServerRequest();
        $request = $request->withHeader('REMOTE_ADDR', '127.0.0.1');

        $event = new Event(new Configuration());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertEquals(['ip_address' => '127.0.0.1', 'foo' => 'bar'], $eventArg->getUserContext());

            ++$invokationCount;
        };

        $middleware = new UserDataCollectorMiddleware($context);
        $middleware($event, $callback, $request);

        $this->assertEquals(1, $invokationCount);
    }
}
