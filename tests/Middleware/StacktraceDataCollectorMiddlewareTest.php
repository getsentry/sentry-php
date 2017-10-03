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
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Middleware\StacktraceDataCollectorMiddleware;
use Raven\Stacktrace;

class StacktraceDataCollectorMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getConfig());

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertInstanceOf(Stacktrace::class, $eventArg->getStacktrace());

            ++$invokationCount;
        };

        $middleware = new StacktraceDataCollectorMiddleware($client);
        $middleware($event, $callback, null, new \Exception());

        $this->assertEquals(1, $invokationCount);
    }
}
