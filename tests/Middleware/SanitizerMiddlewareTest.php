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
use Sentry\Middleware\SanitizerMiddleware;
use Sentry\Serializer;

class SanitizerMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $event = new Event(new Configuration());
        $event->setRequest(['bar' => 'baz']);
        $event->getUserContext()->replaceData(['foo' => 'bar']);
        $event->getTagsContext()->replaceData(['foo', 'bar']);
        $event->getServerOsContext()->replaceData(['name' => 'foo']);
        $event->getRuntimeContext()->replaceData(['name' => 'baz']);
        $event->getExtraContext()->replaceData(['baz' => 'foo']);

        /** @var Serializer|\PHPUnit_Framework_MockObject_MockObject $sanitizer */
        $sanitizer = $this->createMock(Serializer::class);
        $sanitizer->expects($this->exactly(6))
            ->method('serialize')
            ->willReturnCallback(function ($eventData) {
                foreach ($eventData as $key => $value) {
                    $eventData[$key] = strrev($value);
                }

                return $eventData;
            });

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            $this->assertArraySubset(['bar' => 'zab'], $eventArg->getRequest());
            $this->assertArraySubset(['foo' => 'rab'], $eventArg->getUserContext());
            $this->assertArraySubset(['name' => 'zab'], $eventArg->getRuntimeContext());
            $this->assertArraySubset(['name' => 'oof'], $eventArg->getServerOsContext());
            $this->assertArraySubset(['baz' => 'oof'], $eventArg->getExtraContext());
            $this->assertArraySubset(['oof', 'rab'], $eventArg->getTagsContext());

            $callbackInvoked = true;
        };

        $middleware = new SanitizerMiddleware($sanitizer);
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }
}
