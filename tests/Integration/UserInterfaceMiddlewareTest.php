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

use Sentry\Event;
use Sentry\Integration\UserIntegration;
use Sentry\Options;
use Zend\Diactoros\ServerRequest;

class UserInterfaceMiddlewareTest extends MiddlewareTestCase
{
    public function testInvoke()
    {
        $event = new Event(new Options());
        $event->getUserContext()->setData(['foo' => 'bar']);

        $middleware = new UserIntegration();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertArrayNotHasKey('ip_address', $returnedEvent->getUserContext());
    }

    public function testInvokeWithRequest()
    {
        $event = new Event(new Options());
        $event->getUserContext()->setData(['foo' => 'bar']);

        $request = new ServerRequest();
        $request = $request->withHeader('REMOTE_ADDR', '127.0.0.1');

        $middleware = new UserIntegration();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event, $request);

        $this->assertEquals(['ip_address' => '127.0.0.1', 'foo' => 'bar'], $returnedEvent->getUserContext()->toArray());
    }
}
