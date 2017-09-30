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
use Raven\Middleware\ContextDataCollectorMiddleware;

class ContextDataCollectorMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $context = new Context();
        $context->setTag('bar', 'foo');
        $context->mergeUserData(['foo' => 'bar']);
        $context->mergeExtraData(['bar' => 'baz']);

        $configuration = new Configuration();
        $event = new Event($configuration);

        $invokationCount = 0;
        $callback = function (Event $eventArg) use ($event, &$invokationCount) {
            $this->assertNotSame($event, $eventArg);
            $this->assertEquals(['bar' => 'foo', 'foobar' => 'barfoo'], $eventArg->getTagsContext());
            $this->assertEquals(['foo' => 'bar', 'baz' => 'foo'], $eventArg->getUserContext());
            $this->assertEquals(['bar' => 'baz', 'barbaz' => 'bazbar'], $eventArg->getExtraContext());
            $this->assertEquals(['foo' => 'bar'], $eventArg->getServerOsContext());
            $this->assertEquals(['bar' => 'foo'], $eventArg->getRuntimeContext());

            ++$invokationCount;
        };

        $middleware = new ContextDataCollectorMiddleware($context);
        $middleware($event, $callback, null, null, [
            'tags_context' => ['foobar' => 'barfoo'],
            'extra_context' => ['barbaz' => 'bazbar'],
            'server_os_context' => ['foo' => 'bar'],
            'runtime_context' => ['bar' => 'foo'],
            'user_context' => ['baz' => 'foo'],
        ]);

        $this->assertEquals(1, $invokationCount);
    }
}
