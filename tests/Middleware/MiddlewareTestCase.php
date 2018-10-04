<?php

namespace Sentry\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Configuration;
use Sentry\Event;

abstract class MiddlewareTestCase extends TestCase
{
    /**
     * @param callable $middleware
     * @param Event|null $event
     * @return Event The event returned by the middleware
     */
    protected function assertMiddlewareInvokesNextCorrectly(callable $middleware, Event $event = null)
    {
        $exception = new \Exception('Test exception');
        $callbackInvoked = false;
        $callback = function (
            Event $passedEvent,
            $passedRequest = null,
            $passedException = null
        ) use ($exception, &$callbackInvoked) {
            $this->assertInstanceOf(ServerRequestInterface::class, $passedRequest);
            $this->assertSame($exception, $passedException, 'Wrong exception passed through');

            $callbackInvoked = true;

            return $passedEvent;
        };

        if (! $event) {
            $event = new Event($this->createMock(Configuration::class));
        }
        $request = $this->createMock(ServerRequestInterface::class);

        $returnedEvent = $middleware($event, $callback, $request, $exception);

        $this->assertTrue($callbackInvoked, 'Next middleware was not invoked');
        $this->assertSame($event, $returnedEvent, 'Middleware must return a ' . Event::class);

        return $returnedEvent;
    }
}
