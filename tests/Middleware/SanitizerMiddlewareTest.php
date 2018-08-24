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
use Raven\Middleware\SanitizerMiddleware;
use Raven\Serializer;

class SanitizerMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $event = new Event(new Configuration());
        $event->setRequest(['bar' => 'baz']);
        $event->setUserContext(['foo' => 'bar']);
        $event->setTagsContext(['foo', 'bar']);
        $event->setServerOsContext(['bar' => 'foo']);
        $event->setRuntimeContext(['foo' => 'baz']);
        $event->setExtraContext(['baz' => 'foo']);

        /** @var Serializer|\PHPUnit_Framework_MockObject_MockObject $sanitizer */
        $sanitizer = $this->createMock(Serializer::class);
        $sanitizer->expects($this->exactly(6))
            ->method('serialize')
            ->withConsecutive(
                [
                    $event->getRequest(),
                    5,
                ],
                [
                    $event->getUserContext(),
                ],
                [
                    $event->getRuntimeContext(),
                ],
                [
                    $event->getServerOsContext(),
                ],
                [
                    $event->getExtraContext(),
                ],
                [
                    $event->getTagsContext(),
                ]
            )
            ->willReturnCallback(function ($eventData) {
                // This is here just because otherwise the event object will
                // not be updated if the new value being set is the same as
                // the previous one
                return array_flip($eventData);
            });

        $callbackInvoked = false;
        $callback = function (Event $eventArg) use (&$callbackInvoked) {
            $this->assertEquals(['baz' => 'bar'], $eventArg->getRequest());
            $this->assertEquals(['bar' => 'foo'], $eventArg->getUserContext());
            $this->assertEquals(['baz' => 'foo'], $eventArg->getRuntimeContext());
            $this->assertEquals(['foo' => 'bar'], $eventArg->getServerOsContext());
            $this->assertEquals(['foo' => 'baz'], $eventArg->getExtraContext());
            $this->assertEquals(['foo' => 0, 'bar' => 1], $eventArg->getTagsContext());

            $callbackInvoked = true;
        };

        $middleware = new SanitizerMiddleware($sanitizer);
        $middleware($event, $callback);

        $this->assertTrue($callbackInvoked);
    }
}
