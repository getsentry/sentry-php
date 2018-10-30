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

use Sentry\Event;
use Sentry\Integration\SanitizerMiddleware;
use Sentry\Options;
use Sentry\Serializer;

class SanitizerMiddlewareTest extends MiddlewareTestCase
{
    public function testInvoke()
    {
        $event = new Event(new Options());
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

        $middleware = new SanitizerMiddleware($sanitizer);

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertArraySubset(['bar' => 'zab'], $returnedEvent->getRequest());
        $this->assertArraySubset(['foo' => 'rab'], $returnedEvent->getUserContext());
        $this->assertArraySubset(['name' => 'zab'], $returnedEvent->getRuntimeContext());
        $this->assertArraySubset(['name' => 'oof'], $returnedEvent->getServerOsContext());
        $this->assertArraySubset(['baz' => 'oof'], $returnedEvent->getExtraContext());
        $this->assertArraySubset(['oof', 'rab'], $returnedEvent->getTagsContext());
    }
}
