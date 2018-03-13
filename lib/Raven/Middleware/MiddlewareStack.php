<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Raven\Event;

/**
 * This class implements a stack of middlewares that can be sorted by priority.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
class MiddlewareStack
{
    /**
     * @var callable The handler that will end the middleware call stack
     */
    private $handler;

    /**
     * @var array<int, MiddlewareInterface[]> The list of middlewares
     */
    private $stack = [];

    /**
     * @var callable The tip of the middleware call stack
     */
    private $middlewareStackTip;

    /**
     * @var bool Whether the stack of middleware callables is locked
     */
    private $stackLocked = false;

    /**
     * Constructor.
     *
     * @param callable $handler The handler that will end the middleware call stack
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Invokes the handler stack as a composed handler.
     *
     * @param Event                       $event     The event being processed
     * @param ServerRequestInterface|null $request   The request, if available
     * @param \Throwable|\Exception|null  $exception The thrown exception, if available
     * @param array                       $payload   Additional data
     *
     * @return Event
     */
    public function executeStack(Event $event, ServerRequestInterface $request = null, $exception = null, array $payload = [])
    {
        $handler = $this->resolve();

        $this->stackLocked = true;

        $event = $handler($event, $request, $exception, $payload);

        $this->stackLocked = false;

        if (!$event instanceof Event) {
            throw new \UnexpectedValueException(sprintf('Middleware must return an instance of the "%s" class.', Event::class));
        }

        return $event;
    }

    /**
     * Adds a new middleware with the given priority to the stack.
     *
     * @param callable $middleware The middleware instance
     * @param int      $priority   The priority. The higher this value, the
     *                             earlier a processor will be executed in
     *                             the chain (defaults to 0)
     *
     * @throws \RuntimeException If the method is called while the stack is dequeuing
     */
    public function addMiddleware(callable $middleware, $priority = 0)
    {
        if ($this->stackLocked) {
            throw new \RuntimeException('Middleware can\'t be added once the stack is dequeuing.');
        }

        $this->middlewareStackTip = null;

        $this->stack[$priority][] = $middleware;
    }

    /**
     * Removes the given middleware from the stack.
     *
     * @param callable $middleware The middleware instance
     *
     * @throws \RuntimeException If the method is called while the stack is dequeuing
     */
    public function removeMiddleware(callable $middleware)
    {
        if ($this->stackLocked) {
            throw new \RuntimeException('Middleware can\'t be removed once the stack is dequeuing.');
        }

        $this->middlewareStackTip = null;

        foreach ($this->stack as $priority => $middlewares) {
            $this->stack[$priority] = array_filter($middlewares, function ($item) use ($middleware) {
                return $middleware !== $item;
            });
        }
    }

    /**
     * Resolves the stack of middleware callables into a chain where each middleware
     * will call the next one in order of priority.
     *
     * @return callable
     */
    private function resolve()
    {
        if (null === $this->middlewareStackTip) {
            $prev = $this->handler;

            ksort($this->stack);

            if (!empty($this->stack)) {
                foreach (array_merge(...$this->stack) as $middleware) {
                    $prev = function (Event $event, ServerRequestInterface $request = null, $exception = null, array $payload = []) use ($middleware, $prev) {
                        $event = $middleware($event, $prev, $request, $exception, $payload);

                        if (!$event instanceof Event) {
                            throw new \UnexpectedValueException(sprintf('Middleware must return an instance of the "%s" class.', Event::class));
                        }

                        return $event;
                    };
                }
            }

            $this->middlewareStackTip = $prev;
        }

        return $this->middlewareStackTip;
    }
}
