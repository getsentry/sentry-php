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

use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Integration\RemoveStacktraceContextMiddleware;
use Sentry\Stacktrace;

class RemoveStacktraceContextMiddlewareTest extends MiddlewareTestCase
{
    /**
     * @var ClientInterface
     */
    protected $client;

    protected function setUp()
    {
        $this->client = ClientBuilder::create(['auto_log_stacks' => true])
            ->getClient();
    }

    public function testInvoke()
    {
        $exception = new \Exception();

        $event = new Event($this->client->getOptions());
        $event->setStacktrace(Stacktrace::createFromBacktrace($this->client, $exception->getTrace(), $exception->getFile(), $exception->getLine()));

        $middleware = new RemoveStacktraceContextMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertNotNull($returnedEvent->getStacktrace());

        foreach ($returnedEvent->getStacktrace()->getFrames() as $frame) {
            $this->assertEmpty($frame->getPreContext());
            $this->assertNull($frame->getContextLine());
            $this->assertEmpty($frame->getPostContext());
        }
    }

    public function testInvokeWithPreviousException()
    {
        $exception1 = new \Exception();
        $exception2 = new \Exception('foo', 0, $exception1);

        $event = new Event($this->client->getOptions());
        $event->setStacktrace(Stacktrace::createFromBacktrace($this->client, $exception2->getTrace(), $exception2->getFile(), $exception2->getLine()));

        $middleware = new RemoveStacktraceContextMiddleware();

        $returnedEvent = $this->assertMiddlewareInvokesNext($middleware, $event);

        $this->assertNotNull($returnedEvent->getStacktrace());

        foreach ($returnedEvent->getStacktrace()->getFrames() as $frame) {
            $this->assertEmpty($frame->getPreContext());
            $this->assertNull($frame->getContextLine());
            $this->assertEmpty($frame->getPostContext());
        }
    }
}
