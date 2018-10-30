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
use Psr\Http\Message\ServerRequestInterface;
use Sentry\Event;
use Sentry\Integration\IntegrationStack;
use Sentry\Options;

class MiddlewareStackTest extends TestCase
{
    public function testExecuteStack()
    {
        $event = new Event(new Options());

        /** @var ServerRequestInterface|\PHPUnit_Framework_MockObject_MockObject $capturedRequest */
        $capturedRequest = $this->createMock(ServerRequestInterface::class);

        $capturedException = new \Exception();
        $capturedPayload = ['foo' => 'bar'];

        $handlerCalled = false;
        $handler = function (Event $event, ServerRequestInterface $request = null, $exception = null, array $payload = []) use (&$handlerCalled, $capturedRequest, $capturedException, $capturedPayload) {
            // These asserts verify that the arguments passed through all
            // middlewares without getting lost
            $this->assertSame($capturedRequest, $request);
            $this->assertSame($capturedException, $exception);
            $this->assertSame($capturedPayload, $payload);

            $handlerCalled = true;

            return $event;
        };

        $middlewareCalls = [false, false];

        $middlewareStack = new IntegrationStack($handler);
        $middlewareStack->addMiddleware($this->createMiddlewareAssertingInvokation($middlewareCalls[0]));
        $middlewareStack->addMiddleware($this->createMiddlewareAssertingInvokation($middlewareCalls[1]));

        $middlewareStack->executeStack($event, $capturedRequest, $capturedException, $capturedPayload);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains(false, $middlewareCalls);
    }

    public function testAddMiddleware()
    {
        $middlewareCalls = [];

        $handler = function (Event $event) use (&$middlewareCalls) {
            $middlewareCalls[] = 4;

            return $event;
        };

        $middleware1 = function (Event $event, callable $next) use (&$middlewareCalls) {
            $middlewareCalls[] = 1;

            return $next($event);
        };

        $middleware2 = function (Event $event, callable $next) use (&$middlewareCalls) {
            $middlewareCalls[] = 2;

            return $next($event);
        };

        $middleware3 = function (Event $event, callable $next) use (&$middlewareCalls) {
            $middlewareCalls[] = 3;

            return $next($event);
        };

        $middlewareStack = new IntegrationStack($handler);
        $middlewareStack->addMiddleware($middleware1, -10);
        $middlewareStack->addMiddleware($middleware2);
        $middlewareStack->addMiddleware($middleware3, -10);

        $middlewareStack->executeStack(new Event(new Options()));

        $this->assertEquals([2, 3, 1, 4], $middlewareCalls);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Middleware can't be added once the stack is dequeuing.
     */
    public function testAddMiddlewareThrowsWhileStackIsRunning()
    {
        /** @var IntegrationStack $middlewareStack */
        $middlewareStack = null;

        $middlewareStack = new IntegrationStack(function () use (&$middlewareStack) {
            $middlewareStack->addMiddleware(function () {
                // Returning something is not important as the expected exception
                // should be thrown before this point is ever reached
            });
        });

        $middlewareStack->executeStack(new Event(new Options()));
    }

    public function testRemoveMiddleware()
    {
        $middlewareCalls = [];

        $middleware1 = function (Event $event, callable $next) use (&$middlewareCalls) {
            $middlewareCalls[] = 1;

            return $next($event);
        };

        $middleware2 = function (Event $event, callable $next) use (&$middlewareCalls) {
            $middlewareCalls[] = 2;

            return $next($event);
        };

        $middleware3 = function (Event $event, callable $next) use (&$middlewareCalls) {
            $middlewareCalls[] = 3;

            return $next($event);
        };

        $middlewareStack = new IntegrationStack(function (Event $event) use (&$middlewareCalls) {
            $middlewareCalls[] = 4;

            return $event;
        });

        $this->assertFalse($middlewareStack->removeMiddleware($middleware1));

        $middlewareStack->addMiddleware($middleware1, -10);
        $middlewareStack->addMiddleware($middleware2);
        $middlewareStack->addMiddleware($middleware3, -10);

        $this->assertTrue($middlewareStack->removeMiddleware($middleware3));

        $middlewareStack->executeStack(new Event(new Options()));

        $this->assertEquals([2, 1, 4], $middlewareCalls);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Middleware can't be removed once the stack is dequeuing.
     */
    public function testRemoveMiddlewareThrowsWhileStackIsRunning()
    {
        /** @var IntegrationStack $middlewareStack */
        $middlewareStack = null;

        $middlewareStack = new IntegrationStack(function () use (&$middlewareStack) {
            $middlewareStack->removeMiddleware(function () {
                // Returning something is not important as the expected exception
                // should be thrown before this point is ever reached
            });
        });

        $middlewareStack->executeStack(new Event(new Options()));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Middleware must return an instance of the "Sentry\Event" class.
     */
    public function testMiddlewareThrowsWhenBadValueIsReturned()
    {
        $event = new Event(new Options());
        $middlewareStack = new IntegrationStack(function (Event $event) {
            return $event;
        });

        $middlewareStack->addMiddleware(function () {
            // Return nothing so that the expected exception is triggered
        });

        $middlewareStack->executeStack($event);
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage Middleware must return an instance of the "Sentry\Event" class.
     */
    public function testMiddlewareThrowsWhenBadValueIsReturnedFromHandler()
    {
        $event = new Event(new Options());
        $middlewareStack = new IntegrationStack(function () {
            // Return nothing so that the expected exception is triggered
        });

        $middlewareStack->executeStack($event);
    }

    protected function createMiddlewareAssertingInvokation(&$wasCalled)
    {
        return function (Event $event, callable $next, ServerRequestInterface $request = null, $exception = null, array $payload = []) use (&$wasCalled) {
            $wasCalled = true;

            return $next($event, $request, $exception, $payload);
        };
    }
}
