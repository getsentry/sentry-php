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
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\Options;

abstract class MiddlewareTestCase extends TestCase
{
    protected function assertMiddlewareInvokesNext(callable $middleware, Event $event = null, ServerRequestInterface $request = null, \Exception $exception = null, array $payload = []): Event
    {
        $callbackInvoked = false;
        $callback = function (Event $passedEvent, ServerRequestInterface $passedRequest = null, $passedException = null) use (&$callbackInvoked) {
            if ($passedException) {
                $this->assertInstanceOf(\Exception::class, $passedException);
            }

            $callbackInvoked = true;

            return $passedEvent;
        };

        if (null === $event) {
            $event = new Event($this->createMock(Options::class));
        }

        if (null === $request) {
            $request = $this->createMock(ServerRequestInterface::class);
        }

        if (null === $exception) {
            $exception = new \Exception();
        }

        $returnedEvent = $middleware($event, $callback, $request, $exception, $payload);

        $this->assertTrue($callbackInvoked, 'Next middleware was not invoked');
        $this->assertSame($event, $returnedEvent);

        return $returnedEvent;
    }
}
